<?php

namespace App\Domain\Shared\Services;

use App\Domain\Organization\Models\Branch;
use App\Domain\Shared\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentNumberService
{
    public function next(
        Branch $branch,
        string $documentType,
        string $prefix,
        CarbonInterface $effectiveAt,
    ): string {
        if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $documentType) !== 1) {
            throw new InvalidArgumentException('Jenis dokumen tidak valid.');
        }

        if (preg_match('/^[A-Z0-9-]{2,12}$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Prefix dokumen tidak valid.');
        }

        $localDate = $effectiveAt->copy()->setTimezone($branch->timezone);

        return DB::transaction(function () use ($branch, $documentType, $prefix, $localDate): string {
            $scope = [
                'branch_id' => $branch->getKey(),
                'document_type' => $documentType,
                'year' => (int) $localDate->format('Y'),
                'month' => (int) $localDate->format('m'),
            ];
            $now = now();

            DB::table('document_sequences')->insertOrIgnore([
                ...$scope,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DocumentSequence::query()
                ->where($scope)
                ->lockForUpdate()
                ->firstOrFail();
            $nextNumber = $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            return sprintf(
                '%s/%s/%s/%06d',
                $prefix,
                $localDate->format('Y'),
                $localDate->format('m'),
                $nextNumber,
            );
        }, 3);
    }
}
