<?php

namespace Tests\Feature\Domain\Access\Services;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Services\ApprovalService;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_request_can_be_approved_within_configured_limit(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $requester = User::factory()->withRole('cashier')->forBranch($branch)->create();
        $approver = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        ApprovalLimit::factory()->create([
            'branch_id' => $branch->id,
            'role_id' => Role::query()->where('code', 'store_manager')->value('id'),
            'permission_id' => Permission::query()->where('code', 'approvals.approve')->value('id'),
            'minimum_amount' => '0.00',
            'maximum_amount' => '1000000.00',
        ]);

        $service = app(ApprovalService::class);
        $approval = $service->request(
            $requester,
            $branch,
            'approvals.approve',
            'test_transaction',
            'TRX-001',
            '750000.00',
            'Transaksi memerlukan otorisasi manager',
            'approval-key-001',
        );
        $approved = $service->approve($approval, $approver, 'Nominal dan bukti telah diperiksa');

        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $approved->status);
        $this->assertTrue($approved->approver->is($approver));
        $this->assertNotNull($approved->decided_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approval.requested',
            'entity_id' => $approval->public_id,
            'actor_id' => $requester->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approval.approved',
            'entity_id' => $approval->public_id,
            'actor_id' => $approver->id,
            'reason' => 'Nominal dan bukti telah diperiksa',
        ]);
    }

    public function test_approval_above_limit_is_rejected_without_changing_request(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $requester = User::factory()->withRole('cashier')->forBranch($branch)->create();
        $approver = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        ApprovalLimit::factory()->create([
            'branch_id' => $branch->id,
            'role_id' => Role::query()->where('code', 'store_manager')->value('id'),
            'permission_id' => Permission::query()->where('code', 'approvals.approve')->value('id'),
            'maximum_amount' => '1000000.00',
        ]);
        $service = app(ApprovalService::class);
        $approval = $service->request(
            $requester,
            $branch,
            'approvals.approve',
            'test_transaction',
            'TRX-002',
            '1000000.01',
            'Nominal di atas batas manager',
        );

        try {
            $service->approve($approval, $approver, 'Mencoba melewati batas');
            $this->fail('AuthorizationException tidak dilempar.');
        } catch (AuthorizationException) {
            $this->assertSame(ApprovalRequest::STATUS_PENDING, $approval->fresh()->status);
            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'approval.approved',
                'entity_id' => $approval->public_id,
            ]);
        }
    }

    public function test_decided_request_cannot_be_approved_twice(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $requester = User::factory()->withRole('cashier')->forBranch($branch)->create();
        $approver = User::factory()->withRole('super_admin')->create();
        $service = app(ApprovalService::class);
        $approval = $service->request(
            $requester,
            $branch,
            'approvals.approve',
            'test_transaction',
            'TRX-003',
            '500000.00',
            'Pengujian keputusan tunggal',
        );
        $service->approve($approval, $approver, 'Disetujui satu kali');

        $this->expectException(DomainException::class);

        $service->approve($approval, $approver, 'Percobaan keputusan kedua');
    }

    public function test_requester_cannot_create_request_for_inaccessible_branch(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $requester = User::factory()->withRole('cashier')->forBranch($ownBranch)->create();

        $this->expectException(AuthorizationException::class);

        app(ApprovalService::class)->request(
            $requester,
            $foreignBranch,
            'approvals.approve',
            'test_transaction',
            'TRX-004',
            '100000.00',
            'Percobaan beda cabang',
        );
    }
}
