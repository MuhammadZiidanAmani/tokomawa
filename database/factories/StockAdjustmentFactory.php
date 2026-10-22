<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockAdjustment> */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'document_number' => 'ADJ/'.fake()->unique()->numerify('######'),
            'branch_id' => $branch,
            'type' => StockAdjustmentType::OpeningBalance,
            'status' => StockAdjustmentStatus::PendingApproval,
            'source_warehouse_id' => null,
            'destination_warehouse_id' => Warehouse::factory()->for($branch),
            'reason' => fake()->sentence(),
            'occurred_at' => now(),
            'approval_request_id' => null,
            'submission_key' => (string) Str::uuid(),
            'created_by' => User::factory(),
            'submitted_by' => null,
            'approved_by' => null,
            'posted_by' => null,
        ];
    }
}
