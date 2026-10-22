<?php

namespace Tests\Feature\Domain\Access\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_records_actor_branch_roles_reason_and_masked_snapshots(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $actor = User::factory()->withRole('owner')->forBranch($branch)->create();

        $auditLog = app(AuditLogger::class)->record(
            new AuditContext($actor, $branch, 'Perubahan terotorisasi', '127.0.0.1', 'PHPUnit'),
            'security.tested',
            'user',
            $actor->id,
            before: ['password' => 'rahasia', 'profile' => ['api_token' => 'token-asli']],
            after: ['name' => 'Pemilik'],
        );

        $this->assertSame(['owner'], $auditLog->role_snapshot);
        $this->assertSame('[MASKED]', $auditLog->before_json['password']);
        $this->assertSame('[MASKED]', $auditLog->before_json['profile']['api_token']);
        $this->assertSame('Perubahan terotorisasi', $auditLog->reason);
        $this->assertSame($branch->id, $auditLog->branch_id);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $auditLog = AuditLog::query()->create([
            'id' => '01J00000000000000000000000',
            'role_snapshot' => [],
            'action' => 'test.created',
            'entity_type' => 'test',
            'entity_id' => '1',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $auditLog->update(['reason' => 'Tidak boleh']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $auditLog = AuditLog::query()->create([
            'id' => '01J00000000000000000000001',
            'role_snapshot' => [],
            'action' => 'test.created',
            'entity_type' => 'test',
            'entity_id' => '1',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $auditLog->delete();
    }
}
