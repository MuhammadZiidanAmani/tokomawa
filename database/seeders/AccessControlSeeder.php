<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = collect((array) config('access.permissions'))
            ->mapWithKeys(function (array $definition, string $code): array {
                $permission = Permission::query()->updateOrCreate(
                    ['code' => $code],
                    ['name' => $definition['name'], 'module' => $definition['module']],
                );

                return [$code => $permission];
            });

        foreach ((array) config('access.roles') as $code => $definition) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'is_system' => true, 'is_active' => true],
            );

            $permissionCodes = $definition['permissions'] === ['*']
                ? $permissions->keys()
                : collect($definition['permissions']);

            $role->permissions()->sync(
                $permissions->only($permissionCodes->all())->pluck('id')->all(),
            );
        }
    }
}
