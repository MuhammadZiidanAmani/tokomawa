<?php

namespace Tests\Feature\Http\Controllers\Settings;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Shared\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_create_first_branch_with_audit_and_outbox(): void
    {
        $this->seed();
        $superAdmin = User::factory()->withRole('super_admin')->create();

        $response = $this->actingAs($superAdmin)->post(route('settings.branches.store'), [
            'code' => ' jkt-01 ',
            'name' => 'Cabang Jakarta',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertRedirect()->assertSessionHas('status', 'Cabang berhasil dibuat.');
        $branch = Branch::query()->where('code', 'JKT-01')->first();
        $this->assertNotNull($branch);
        $this->assertTrue($superAdmin->branches()->whereKey($branch)->wherePivot('is_default', true)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created', 'entity_id' => $branch->public_id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'organization.branch_created']);
    }

    public function test_duplicate_branch_code_is_rejected_without_side_effects(): void
    {
        $this->seed();
        Branch::factory()->create(['code' => 'JKT-01']);
        $superAdmin = User::factory()->withRole('super_admin')->create();

        $response = $this->actingAs($superAdmin)->from(route('settings.governance'))->post(route('settings.branches.store'), [
            'code' => 'jkt-01',
            'name' => 'Duplikat',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response
            ->assertRedirect(route('settings.governance'))
            ->assertSessionHasErrors(['code' => 'Kode cabang sudah digunakan.']);
        $this->assertSame(1, Branch::query()->where('code', 'JKT-01')->count());
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, OutboxEvent::query()->count());
    }

    public function test_manager_can_create_warehouse_and_register_in_own_branch(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();

        $warehouseResponse = $this->actingAs($manager)->post(route('settings.warehouses.store', $branch), [
            'code' => 'gdg-01',
            'name' => 'Gudang Utama',
        ]);
        $registerResponse = $this->actingAs($manager)->post(route('settings.registers.store', $branch), [
            'code' => 'reg-01',
            'name' => 'Kasir 01',
        ]);

        $warehouseResponse->assertRedirect()->assertSessionHas('status');
        $registerResponse->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('warehouses', ['branch_id' => $branch->id, 'code' => 'GDG-01']);
        $this->assertDatabaseHas('registers', ['branch_id' => $branch->id, 'code' => 'REG-01']);
        $this->assertSame(1, Warehouse::query()->count());
        $this->assertSame(1, Register::query()->count());
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_cross_branch_organization_write_returns_not_found_without_mutation(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($ownBranch)->create();

        $response = $this->actingAs($manager)->post(route('settings.warehouses.store', $foreignBranch), [
            'code' => 'GDG-X',
            'name' => 'Gudang Rahasia',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('warehouses', ['name' => 'Gudang Rahasia']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_manager_can_configure_approval_limit_for_own_branch_with_audit(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $managerRole = Role::query()->where('code', 'store_manager')->firstOrFail();
        $permission = Permission::query()->where('code', 'approvals.approve')->firstOrFail();

        $response = $this->actingAs($manager)->post(route('settings.approval-limits.store', $branch), [
            'role_id' => $managerRole->id,
            'permission_id' => $permission->id,
            'minimum_amount' => '0',
            'maximum_amount' => '2500000.00',
            'reason' => 'Batas awal otorisasi manager',
        ]);

        $response->assertRedirect()->assertSessionHas('status', 'Batas persetujuan berhasil disimpan.');
        $this->assertDatabaseHas('approval_limits', [
            'branch_id' => $branch->id,
            'role_id' => $managerRole->id,
            'permission_id' => $permission->id,
            'maximum_amount' => 2500000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approval_limit.saved',
            'branch_id' => $branch->id,
            'reason' => 'Batas awal otorisasi manager',
        ]);
    }

    public function test_invalid_approval_range_is_rejected_without_side_effects(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();

        $response = $this->actingAs($manager)->from(route('settings.governance'))->post(
            route('settings.approval-limits.store', $branch),
            [
                'role_id' => Role::query()->where('code', 'store_manager')->value('id'),
                'permission_id' => Permission::query()->where('code', 'approvals.approve')->value('id'),
                'minimum_amount' => '2000000.00',
                'maximum_amount' => '1000000.00',
                'reason' => 'Rentang yang tidak valid',
            ],
        );

        $response->assertRedirect(route('settings.governance'))->assertSessionHasErrors('maximum_amount');
        $this->assertDatabaseCount('approval_limits', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
