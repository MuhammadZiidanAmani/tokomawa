<?php

namespace Tests\Feature\Http\Controllers\Settings;

use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccessAssignmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_assign_role_and_branch_with_audit_trail(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $owner = User::factory()->withRole('owner')->forBranch($branch)->create();
        $target = User::factory()->create();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $response = $this->actingAs($owner)->post(route('settings.access.store'), [
            'user_id' => $target->id,
            'role_ids' => [$cashierRole->id],
            'branch_ids' => [$branch->id],
            'reason' => 'Penempatan kasir baru',
            'is_system' => true,
        ]);

        $response->assertRedirect()->assertSessionHas('status', 'Hak akses pengguna berhasil diperbarui.');
        $this->assertTrue($target->hasRole('cashier'));
        $this->assertTrue($target->branches()->whereKey($branch)->wherePivot('is_default', true)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.assignment_updated',
            'entity_id' => (string) $target->id,
            'reason' => 'Penempatan kasir baru',
        ]);
        $this->assertTrue($cashierRole->fresh()->is_system);
    }

    public function test_manager_without_access_manage_permission_is_forbidden(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $target = User::factory()->create();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $response = $this->actingAs($manager)->post(route('settings.access.store'), [
            'user_id' => $target->id,
            'role_ids' => [$cashierRole->id],
            'branch_ids' => [$branch->id],
            'reason' => 'Percobaan tanpa izin',
        ]);

        $response->assertForbidden();
        $this->assertFalse($target->roles()->exists());
    }

    public function test_owner_cannot_assign_a_branch_outside_own_scope(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $owner = User::factory()->withRole('owner')->forBranch($ownBranch)->create();
        $target = User::factory()->create();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $response = $this->actingAs($owner)->from(route('settings.governance'))->post(route('settings.access.store'), [
            'user_id' => $target->id,
            'role_ids' => [$cashierRole->id],
            'branch_ids' => [$foreignBranch->id],
            'reason' => 'Penempatan cabang lain',
        ]);

        $response->assertRedirect(route('settings.governance'))->assertSessionHasErrors('branch_ids');
        $this->assertFalse($target->branches()->exists());
    }

    public function test_owner_cannot_escalate_a_user_to_super_admin(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $owner = User::factory()->withRole('owner')->forBranch($branch)->create();
        $target = User::factory()->forBranch($branch)->create();
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();

        $response = $this->actingAs($owner)->from(route('settings.governance'))->post(route('settings.access.store'), [
            'user_id' => $target->id,
            'role_ids' => [$superAdminRole->id],
            'branch_ids' => [$branch->id],
            'reason' => 'Percobaan eskalasi akses',
        ]);

        $response->assertRedirect(route('settings.governance'))->assertSessionHasErrors('role_ids');
        $this->assertFalse($target->hasRole('super_admin'));
    }

    public function test_super_admin_cannot_remove_own_super_admin_role(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $superAdmin = User::factory()->withRole('super_admin')->forBranch($branch)->create();
        $ownerRole = Role::query()->where('code', 'owner')->firstOrFail();

        $response = $this->actingAs($superAdmin)->from(route('settings.governance'))->post(route('settings.access.store'), [
            'user_id' => $superAdmin->id,
            'role_ids' => [$ownerRole->id],
            'branch_ids' => [$branch->id],
            'reason' => 'Mencabut akses sendiri',
        ]);

        $response->assertRedirect(route('settings.governance'))->assertSessionHasErrors('role_ids');
        $this->assertTrue($superAdmin->hasRole('super_admin'));
    }
}
