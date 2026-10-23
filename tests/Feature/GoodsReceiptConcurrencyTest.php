<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Shared\Models\DocumentSequence;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GoodsReceiptConcurrencyTest extends TestCase
{
    public function test_two_processes_allocate_unique_numbers_and_cannot_over_receive_same_outstanding(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $actor = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Approved,
            'created_by' => $actor->id, 'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
            'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name,
            'quantity' => '5.000', 'base_quantity' => '5.000', 'unit_price' => '1000.00', 'subtotal' => '5000.00',
        ]);
        $createBarrier = storage_path('framework/testing/'.Str::ulid().'.gr-create');
        $postBarrier = storage_path('framework/testing/'.Str::ulid().'.gr-post');
        File::ensureDirectoryExists(dirname($createBarrier));

        try {
            $createProcesses = [
                $this->createWorker($branch, $warehouse, $actor, $purchaseOrder, $item, 'SJ-RACE-A', $createBarrier),
                $this->createWorker($branch, $warehouse, $actor, $purchaseOrder, $item, 'SJ-RACE-B', $createBarrier),
            ];
            foreach ($createProcesses as $process) {
                $process->start();
            }
            File::put($createBarrier, 'start');
            $created = $this->collectResults($createProcesses);
            $this->assertTrue(collect($created)->every('success'));
            $numbers = array_column($created, 'document_number');
            sort($numbers);
            $this->assertSame(['GR/2026/10/000001', 'GR/2026/10/000002'], $numbers);
            $this->assertCount(2, array_unique($numbers));

            $receipts = GoodsReceipt::query()->orderBy('id')->get();
            $postProcesses = $receipts->map(fn (GoodsReceipt $receipt): Process => $this->postWorker($receipt, $actor, $postBarrier))->all();
            foreach ($postProcesses as $process) {
                $process->start();
            }
            File::put($postBarrier, 'start');
            $posted = $this->collectResults($postProcesses);
            $successes = array_column($posted, 'success');
            sort($successes);
            $this->assertSame([false, true], $successes);
            $this->assertSame('5.000', $item->fresh()->received_base_quantity);
            $this->assertSame(PurchaseOrderStatus::Completed, $purchaseOrder->fresh()->status);
            $this->assertSame('5.000', InventoryBalance::query()->sole()->qty_on_hand);
            $this->assertSame(1, InventoryMovement::query()->count());
            $this->assertSame(1, GoodsReceipt::query()->where('status', 'posted')->count());
            $this->assertSame(1, GoodsReceipt::query()->where('status', 'draft')->count());
            $this->assertSame(2, DocumentSequence::query()->where('document_type', 'goods_receipt')->sole()->last_number);
        } finally {
            File::delete([$createBarrier, $postBarrier]);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    /** @param list<Process> $processes @return list<array<string, mixed>> */
    private function collectResults(array $processes): array
    {
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), trim($process->getErrorOutput().' '.$process->getOutput()));
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    private function createWorker(
        Branch $branch,
        Warehouse $warehouse,
        User $actor,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        string $deliveryNote,
        string $barrier,
    ): Process {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', 'create', (string) $branch->id, (string) $actor->id,
                (string) $purchaseOrder->id, (string) $warehouse->id, (string) $item->id, $deliveryNote, $barrier, (string) Str::uuid()],
            base_path(), $this->workerEnvironment(), timeout: 30,
        );
    }

    private function postWorker(GoodsReceipt $receipt, User $actor, string $barrier): Process
    {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', 'post', (string) $receipt->id, (string) $actor->id, $barrier, (string) Str::uuid()],
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

try {
    if ($argv[1] === 'create') {
        while (! is_file($argv[8])) { usleep(1_000); }
        $branch = \App\Domain\Organization\Models\Branch::query()->findOrFail((int) $argv[2]);
        $actor = \App\Models\User::query()->findOrFail((int) $argv[3]);
        $receipt = $app->make(\App\Domain\Purchasing\Services\GoodsReceiptService::class)->createDraft($actor, $branch, [
            'purchase_order_id' => (int) $argv[4], 'warehouse_id' => (int) $argv[5],
            'supplier_delivery_note' => $argv[7], 'received_at' => '2026-10-23 10:00:00',
            'notes' => 'Uji concurrency create', 'idempotency_key' => $argv[9],
            'items' => [[
                'purchase_order_item_id' => (int) $argv[6], 'quantity' => '5.000',
                'lot_number' => null, 'manufactured_at' => null, 'expires_at' => null, 'notes' => null,
            ]],
        ]);
        echo json_encode(['success' => true, 'document_number' => $receipt->document_number], JSON_THROW_ON_ERROR);
    } else {
        while (! is_file($argv[4])) { usleep(1_000); }
        $receipt = \App\Domain\Purchasing\Models\GoodsReceipt::query()->findOrFail((int) $argv[2]);
        $actor = \App\Models\User::query()->findOrFail((int) $argv[3]);
        $branch = $receipt->branch()->firstOrFail();
        $posted = $app->make(\App\Domain\Purchasing\Services\GoodsReceiptService::class)->post($receipt, $actor, $branch, $argv[5]);
        echo json_encode(['success' => true, 'document_number' => $posted->document_number], JSON_THROW_ON_ERROR);
    }
} catch (\Throwable $exception) {
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;

    }
}
