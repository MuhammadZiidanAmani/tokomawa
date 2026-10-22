<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\DTOs\IdempotencyResult;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Domain\Shared\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  Closure(): array<string, mixed>  $operationCallback
     *
     * @throws JsonException
     */
    public function execute(
        string $scope,
        string $operation,
        string $key,
        array $requestPayload,
        Closure $operationCallback,
        ?User $actor = null,
        int $responseCode = 200,
    ): IdempotencyResult {
        $requestHash = hash('sha256', json_encode(Arr::sortRecursive($requestPayload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $scope,
            $operation,
            $key,
            $requestHash,
            $operationCallback,
            $actor,
            $responseCode,
        ): IdempotencyResult {
            $id = (string) Str::ulid();
            $now = now();
            $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                'id' => $id,
                'scope' => $scope,
                'operation' => $operation,
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => IdempotencyKey::STATUS_PROCESSING,
                'created_by' => $actor?->getKey(),
                'expires_at' => $now->copy()->addDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('operation', $operation)
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inserted === 0) {
                if (! hash_equals($record->request_hash, $requestHash)) {
                    throw new IdempotencyConflictException;
                }

                if ($record->status !== IdempotencyKey::STATUS_COMPLETED) {
                    throw new IdempotencyInProgressException;
                }

                return new IdempotencyResult(
                    payload: $record->response_payload ?? [],
                    responseCode: $record->response_code ?? 200,
                    replayed: true,
                );
            }

            $payload = $operationCallback();
            $record->update([
                'status' => IdempotencyKey::STATUS_COMPLETED,
                'response_code' => $responseCode,
                'response_payload' => $payload,
            ]);

            return new IdempotencyResult($payload, $responseCode, false);
        }, 3);
    }
}
