<?php

namespace Tests\Feature\Domain\Shared\Services;

use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Domain\Shared\Models\IdempotencyKey;
use App\Domain\Shared\Services\IdempotencyService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_same_key_and_payload_replays_stored_response_without_reexecuting_operation(): void
    {
        $service = app(IdempotencyService::class);
        $executions = 0;

        $first = $service->execute(
            'branch:01',
            'sale.create',
            'request-key-001',
            ['items' => [['quantity' => 2, 'sku' => 'SKU-1']], 'customer_id' => 10],
            function () use (&$executions): array {
                $executions++;

                return ['document_number' => 'POS/2026/10/000001'];
            },
            responseCode: 201,
        );
        $replay = $service->execute(
            'branch:01',
            'sale.create',
            'request-key-001',
            ['customer_id' => 10, 'items' => [['sku' => 'SKU-1', 'quantity' => 2]]],
            function () use (&$executions): array {
                $executions++;

                return ['document_number' => 'SHOULD-NOT-RUN'];
            },
            responseCode: 201,
        );

        $this->assertSame(1, $executions);
        $this->assertFalse($first->replayed);
        $this->assertTrue($replay->replayed);
        $this->assertSame(201, $replay->responseCode);
        $this->assertSame($first->payload, $replay->payload);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $service = app(IdempotencyService::class);
        $service->execute(
            'branch:01',
            'sale.create',
            'request-key-002',
            ['total' => '10000.00'],
            fn (): array => ['id' => 1],
        );

        $this->expectException(IdempotencyConflictException::class);

        $service->execute(
            'branch:01',
            'sale.create',
            'request-key-002',
            ['total' => '15000.00'],
            fn (): array => ['id' => 2],
        );
    }

    public function test_processing_key_is_not_executed_again(): void
    {
        IdempotencyKey::query()->create([
            'id' => (string) Str::ulid(),
            'scope' => 'branch:01',
            'operation' => 'sale.create',
            'key' => 'request-key-003',
            'request_hash' => hash('sha256', json_encode(['total' => '10000.00'], JSON_THROW_ON_ERROR)),
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'expires_at' => now()->addDay(),
        ]);

        $this->expectException(IdempotencyInProgressException::class);

        app(IdempotencyService::class)->execute(
            'branch:01',
            'sale.create',
            'request-key-003',
            ['total' => '10000.00'],
            fn (): array => ['id' => 3],
        );
    }

    public function test_failed_operation_rolls_back_idempotency_claim(): void
    {
        try {
            app(IdempotencyService::class)->execute(
                'branch:01',
                'sale.create',
                'request-key-004',
                ['total' => '10000.00'],
                fn (): never => throw new \RuntimeException('Operasi gagal'),
            );
            $this->fail('RuntimeException tidak dilempar.');
        } catch (\RuntimeException) {
            $this->assertDatabaseMissing('idempotency_keys', ['key' => 'request-key-004']);
        }

    }
}
