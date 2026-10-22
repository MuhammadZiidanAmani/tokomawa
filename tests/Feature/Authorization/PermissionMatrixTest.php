<?php

namespace Tests\Feature\Authorization;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeded_roles_match_the_complete_configured_permission_matrix(): void
    {
        $this->seed();

        foreach ((array) config('access.roles') as $roleCode => $definition) {
            $user = User::factory()->withRole($roleCode)->create();

            foreach (array_keys((array) config('access.permissions')) as $permissionCode) {
                $expected = $definition['permissions'] === ['*']
                    || in_array($permissionCode, $definition['permissions'], true);

                $this->assertSame(
                    $expected,
                    Gate::forUser($user)->allows($permissionCode),
                    "Role {$roleCode} tidak sesuai untuk izin {$permissionCode}.",
                );
            }
        }
    }

    public function test_direct_permission_override_takes_precedence_over_role_permission(): void
    {
        $this->seed();
        $user = User::factory()->withRole('owner')->create();
        $permission = Permission::query()->where('code', 'audit.view')->firstOrFail();
        $user->permissions()->attach($permission, ['is_granted' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('audit.view'));

        $user->permissions()->updateExistingPivot($permission, ['is_granted' => true]);

        $this->assertTrue(Gate::forUser($user)->allows('audit.view'));
    }

    public function test_branch_policy_combines_permission_and_branch_scope(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($ownBranch)->create();
        $ownWarehouse = Warehouse::factory()->for($ownBranch)->create();
        $foreignWarehouse = Warehouse::factory()->for($foreignBranch)->create();

        $this->assertTrue(Gate::forUser($manager)->allows('view', $ownWarehouse));
        $this->assertTrue(Gate::forUser($manager)->allows('create', [Warehouse::class, $ownBranch]));
        $this->assertFalse(Gate::forUser($manager)->allows('view', $foreignWarehouse));
        $this->assertFalse(Gate::forUser($manager)->allows('create', [Warehouse::class, $foreignBranch]));
    }

    public function test_access_control_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(8, Role::query()->count());
        $this->assertSame(12, Permission::query()->count());
    }
}
