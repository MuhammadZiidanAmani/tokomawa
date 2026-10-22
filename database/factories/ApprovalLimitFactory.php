<?php

namespace Database\Factories;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalLimit>
 */
class ApprovalLimitFactory extends Factory
{
    protected $model = ApprovalLimit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'role_id' => Role::factory(),
            'permission_id' => Permission::factory(),
            'minimum_amount' => '0.00',
            'maximum_amount' => '1000000.00',
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
