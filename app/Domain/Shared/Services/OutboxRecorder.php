<?php

namespace App\Domain\Shared\Services;

use App\Domain\Organization\Models\Branch;
use App\Domain\Shared\Models\OutboxEvent;
use Illuminate\Support\Str;

class OutboxRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(
        ?Branch $branch,
        string $eventType,
        string $aggregateType,
        string|int $aggregateId,
        array $payload,
    ): OutboxEvent {
        return OutboxEvent::query()->create([
            'id' => (string) Str::ulid(),
            'branch_id' => $branch?->getKey(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'payload' => $payload,
            'occurred_at' => now(),
            'attempts' => 0,
        ]);
    }
}
