<?php

namespace Tests\Feature;

use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\AccountsPayable\Services\PurchaseInvoiceService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PurchaseInvoiceConcurrencyTest extends TestCase
{
    public function test_two_processes_cannot_invoice_the_same_received_quantity(): void
    {
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $actor = User::factory()->withRole('super_admin')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $po = PurchaseOrder::factory()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Completed, 'created_by' => $actor->id]);
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'base_quantity' => '5.000', 'received_base_quantity' => '5.000']);
        $receipt = GoodsReceipt::factory()->create(['branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'purchase_order_id' => $po->id, 'supplier_id' => $supplier->id, 'status' => GoodsReceiptStatus::Posted, 'created_by' => $actor->id]);
        $item = GoodsReceiptItem::factory()->create(['goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '5.000', 'base_quantity' => '5.000', 'unit_price_snapshot' => '1000.00', 'line_total' => '5000.00']);
        $service = app(PurchaseInvoiceService::class);
        $invoices = [];
        foreach (['RACE-A', 'RACE-B'] as $number) {
            $invoices[] = $service->createDraft($actor, $branch, ['supplier_id' => $supplier->id, 'supplier_invoice_number' => $number, 'purchase_type' => 'credit', 'invoice_date' => '2026-10-24', 'due_date' => '2026-11-24', 'idempotency_key' => (string) Str::uuid(), 'items' => [['goods_receipt_item_id' => $item->id, 'base_quantity' => '5.000', 'invoice_unit_price' => '1000.00', 'discount_amount' => '0', 'tax_amount' => '0']]]);
        }
        $barrier = storage_path('framework/testing/'.Str::ulid().'.invoice');
        File::ensureDirectoryExists(dirname($barrier));
        try {
            $processes = array_map(fn (PurchaseInvoice $invoice): Process => $this->worker($invoice, $actor, $barrier), $invoices);
            foreach ($processes as $process) {
                $process->start();
            } File::put($barrier, 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            } $success = array_column($results, 'success');
            sort($success);
            $this->assertSame([false, true], $success);
            $this->assertSame(1, PurchaseInvoice::query()->where('status', 'posted')->count());
            $this->assertDatabaseCount('ap_entries', 1);
            $this->assertDatabaseCount('inventory_movements', 0);
        } finally {
            File::delete($barrier);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function worker(PurchaseInvoice $invoice, User $actor, string $barrier): Process
    {
        $db = config('database.connections.mariadb');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'mariadb', 'DB_URL' => '', 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => (string) $db['database'], 'DB_USERNAME' => (string) $db['username'], 'DB_PASSWORD' => (string) $db['password'], 'DB_SOCKET' => (string) $db['unix_socket'], 'DB_CHARSET' => (string) $db['charset'], 'DB_COLLATION' => (string) $db['collation']];
        $code = <<<'PHP'
require getcwd().'/vendor/autoload.php'; $app=require getcwd().'/bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try { while(!is_file($argv[3])){usleep(1000);} $invoice=\App\Domain\AccountsPayable\Models\PurchaseInvoice::query()->findOrFail((int)$argv[1]); $actor=\App\Models\User::query()->findOrFail((int)$argv[2]); $app->make(\App\Domain\AccountsPayable\Services\PurchaseInvoiceService::class)->post($invoice,$actor,$invoice->branch()->firstOrFail(),$argv[4]); echo json_encode(['success'=>true],JSON_THROW_ON_ERROR); } catch(\Throwable $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR); }
PHP;

        return new Process([PHP_BINARY, '-r', $code, '--', (string) $invoice->id, (string) $actor->id, $barrier, (string) Str::uuid()], base_path(), $env, timeout: 30);
    }
}
