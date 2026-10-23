<?php

namespace Tests\Feature;

use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_cannot_consume_the_same_last_stock(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();

        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $actor = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        InventoryBalance::factory()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => '1.000',
            'avg_unit_cost' => '1000.000000',
            'inventory_value' => '1000.000000',
        ]);
        $adjustments = [
            $this->approvedDecrease($branch, $warehouse, $actor, $product, $unit),
            $this->approvedDecrease($branch, $warehouse, $actor, $product, $unit),
        ];
        $barrierPath = storage_path('framework/testing/'.Str::ulid().'.inventory-start');
        File::ensureDirectoryExists(dirname($barrierPath));
        $processes = [
            $this->worker($adjustments[0], $actor, $barrierPath),
            $this->worker($adjustments[1], $actor, $barrierPath),
        ];

        try {
            foreach ($processes as $process) {
                $process->start();
            }
            File::put($barrierPath, 'start');

            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), trim($process->getErrorOutput().' '.$process->getOutput()));
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            $successes = array_column($results, 'success');
            sort($successes);
            $this->assertSame([false, true], $successes);
            $failure = collect($results)->firstWhere('success', false);
            $this->assertStringContainsString('Stok tersedia', $failure['message']);
            $this->assertSame('0.000', InventoryBalance::query()->sole()->qty_on_hand);
            $this->assertSame(1, InventoryMovement::query()->count());
            $this->assertSame(1, StockAdjustment::query()->where('status', 'posted')->count());
            $this->assertSame(1, StockAdjustment::query()->where('status', 'approved')->count());
        } finally {
            File::delete($barrierPath);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function approvedDecrease(Branch $branch, Warehouse $warehouse, User $actor, Product $product, Unit $unit): StockAdjustment
    {
        $adjustment = StockAdjustment::factory()->create([
            'branch_id' => $branch->id,
            'type' => StockAdjustmentType::Decrease,
            'status' => StockAdjustmentStatus::Approved,
            'source_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => null,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $approval = ApprovalRequest::query()->create([
            'branch_id' => $branch->id,
            'permission_code' => 'inventory.transfer.approve',
            'entity_type' => 'stock_adjustment',
            'entity_id' => $adjustment->public_id,
            'amount' => '0.00',
            'status' => ApprovalRequest::STATUS_APPROVED,
            'reason' => 'Persetujuan uji konkurensi',
            'requested_by' => $actor->id,
            'approved_by' => $actor->id,
            'decision_notes' => 'Disetujui untuk pengujian',
            'requested_at' => now(),
            'decided_at' => now(),
        ]);
        $adjustment->update(['approval_request_id' => $approval->id]);
        $adjustment->items()->create([
            'line_number' => 1,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => '1.000',
            'base_quantity' => '1.000',
            'unit_cost' => null,
            'stock_status' => 'sellable',
        ]);

        return $adjustment->fresh();
    }

    private function worker(StockAdjustment $adjustment, User $actor, string $barrierPath): Process
    {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', (string) $adjustment->id, (string) $actor->id, $barrierPath, (string) Str::uuid()],
            base_path(),
            $this->workerEnvironment(),
            timeout: 30,
        );
    }

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        /** @var array<string, mixed> $database */
        $database = config('database.connections.mariadb');

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mariadb',
            'DB_URL' => '',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'DB_SOCKET' => (string) $database['unix_socket'],
            'DB_CHARSET' => (string) $database['charset'],
            'DB_COLLATION' => (string) $database['collation'],
        ];
    }

    private function workerCode(): string
    {
        return <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

while (! is_file($argv[3])) {
    usleep(1_000);
}

try {
    $adjustment = \App\Domain\Inventory\Models\StockAdjustment::query()->findOrFail((int) $argv[1]);
    $actor = \App\Models\User::query()->findOrFail((int) $argv[2]);
    $result = $app->make(\App\Domain\Inventory\Services\InventoryPostingService::class)
        ->post($adjustment, $actor, $argv[4]);
    echo json_encode(['success' => true, 'document_number' => $result->payload['document_number']], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;

    }
}
