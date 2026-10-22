<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\AuditLog;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditContext $context,
        string $action,
        string $entityType,
        string|int $entityId,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'id' => (string) Str::ulid(),
            'actor_id' => $context->actor?->getKey(),
            'role_snapshot' => $context->actor?->roles()->orderBy('code')->pluck('code')->all() ?? [],
            'branch_id' => $context->branch?->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'before_json' => $this->sanitize($before),
            'after_json' => $this->sanitize($after),
            'reason' => $context->reason,
            'ip_address' => $context->ipAddress,
            'user_agent' => $context->userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|authorization|cookie/i', (string) $key) === 1) {
                $values[$key] = '[MASKED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
