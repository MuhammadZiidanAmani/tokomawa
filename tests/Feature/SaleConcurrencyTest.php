<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Services\CashShiftService;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SaleConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_cannot_sell_the_same_last_stock(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);
        $priceList = PriceList::factory()->create(['is_active' => true]);
        ProductPrice::factory()->create([
            'product_id' => $product->id, 'unit_id' => $unit->id, 'price_list_id' => $priceList->id,
            'amount' => '10000.00', 'effective_from' => now()->subDay(), 'effective_to' => null,
        ]);
        $balance = InventoryBalance::factory()->create([
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'qty_on_hand' => '1.000', 'avg_unit_cost' => '6000.000000', 'inventory_value' => '6000.000000',
        ]);
        $workers = collect([1, 2])->map(function (int $number) use ($branch, $warehouse): array {
            $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();
            $register = Register::factory()->for($branch)->create(['code' => 'RACE-'.$number, 'is_active' => true]);
            $shift = app(CashShiftService::class)->open($cashier, $branch, $register->id, $warehouse->id, '0', (string) Str::uuid());

            return [$cashier, $shift];
        });
        $barrier = storage_path('framework/testing/'.Str::ulid().'.sale-race');
        File::ensureDirectoryExists(dirname($barrier));

        try {
            $processes = $workers->map(fn (array $worker): Process => $this->worker(
                $branch->id, $worker[0]->id, $worker[1]->id, $product->id, $unit->id, $barrier,
            ))->all();
            foreach ($processes as $process) {
                $process->start();
            }
            File::put($barrier, 'start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), trim($process->getErrorOutput().' '.$process->getOutput()));
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $successes = array_column($results, 'success');
            sort($successes);
            $this->assertSame([false, true], $successes);
            $this->assertSame('0.000', $balance->fresh()->qty_on_hand);
            $this->assertSame('0.000000', $balance->fresh()->inventory_value);
            $this->assertDatabaseCount('sales', 1);
            $this->assertDatabaseCount('sale_payments', 1);
            $this->assertDatabaseCount('inventory_movements', 1);
            $this->assertSame(1, CashMovement::query()->where('movement_type', 'sale')->count());
            $this->assertSame('out', InventoryMovement::query()->sole()->direction);
            $this->assertSame('10000.00', Sale::query()->sole()->grand_total);
            $this->assertSame('10000.00', SalePayment::query()->sole()->amount);
        } finally {
            File::delete($barrier);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function worker(int $branchId, int $cashierId, int $shiftId, int $productId, int $unitId, string $barrier): Process
    {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', (string) $branchId, (string) $cashierId, (string) $shiftId,
                (string) $productId, (string) $unitId, $barrier, (string) Str::uuid()],
            base_path(), $this->workerEnvironment(), timeout: 30,
        );
    }

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        /** @var array<string, mixed> $database */
        $database = config('database.connections.mariadb');

        return [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mariadb', 'DB_URL' => '', 'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'], 'DB_PASSWORD' => (string) $database['password'],
            'DB_SOCKET' => (string) $database['unix_socket'], 'DB_CHARSET' => (string) $database['charset'],
            'DB_COLLATION' => (string) $database['collation'],
        ];
    }

    private function workerCode(): string
    {
        return <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! is_file($argv[6])) { usleep(1_000); }
try {
    $branch = \App\Domain\Organization\Models\Branch::query()->findOrFail((int) $argv[1]);
    $actor = \App\Models\User::query()->findOrFail((int) $argv[2]);
    $sale = $app->make(\App\Domain\Sales\Services\SalePostingService::class)->checkout($actor, $branch, [
        'cash_shift_id' => (int) $argv[3], 'cash_tendered' => '10000.00', 'idempotency_key' => $argv[7],
        'items' => [['product_id' => (int) $argv[4], 'unit_id' => (int) $argv[5], 'inventory_batch_id' => null, 'quantity' => '1.000']],
    ]);
    echo json_encode(['success' => true, 'sale' => $sale->document_number], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;
    }
}
