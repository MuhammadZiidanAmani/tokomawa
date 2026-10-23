<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Shared\Models\DocumentSequence;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PurchaseOrderConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_allocate_unique_purchase_order_numbers(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('retail_testing', config('database.connections.mariadb.database'));
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
        $branch = Branch::factory()->create();
        $creator = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);
        $barrierPath = storage_path('framework/testing/'.Str::ulid().'.po-start');
        File::ensureDirectoryExists(dirname($barrierPath));
        $processes = [
            $this->worker($branch, $creator, $supplier, $product, $unit, $barrierPath),
            $this->worker($branch, $creator, $supplier, $product, $unit, $barrierPath),
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

            $this->assertTrue(collect($results)->every('success'));
            $numbers = array_column($results, 'document_number');
            sort($numbers);
            $this->assertSame(['PO/2026/10/000001', 'PO/2026/10/000002'], $numbers);
            $this->assertCount(2, array_unique($numbers));
            $this->assertSame(2, PurchaseOrder::query()->count());
            $this->assertSame(2, DocumentSequence::query()->where('document_type', 'purchase_order')->sole()->last_number);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        } finally {
            File::delete($barrierPath);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    private function worker(Branch $branch, User $creator, Supplier $supplier, Product $product, Unit $unit, string $barrierPath): Process
    {
        return new Process(
            [PHP_BINARY, '-r', $this->workerCode(), '--', (string) $branch->id, (string) $creator->id, (string) $supplier->id, (string) $product->id, (string) $unit->id, $barrierPath, (string) Str::uuid()],
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

while (! is_file($argv[6])) {
    usleep(1_000);
}

try {
    $branch = \App\Domain\Organization\Models\Branch::query()->findOrFail((int) $argv[1]);
    $actor = \App\Models\User::query()->findOrFail((int) $argv[2]);
    $payload = [
        'supplier_id' => (int) $argv[3],
        'order_date' => '2026-10-23',
        'expected_at' => '2026-10-30',
        'notes' => 'Uji konkurensi nomor Purchase Order',
        'idempotency_key' => $argv[7],
        'items' => [[
            'product_id' => (int) $argv[4],
            'unit_id' => (int) $argv[5],
            'quantity' => '1.000',
            'unit_price' => '1000.00',
        ]],
    ];
    $purchaseOrder = $app->make(\App\Domain\Purchasing\Services\PurchaseOrderService::class)
        ->createDraft($actor, $branch, $payload);
    echo json_encode(['success' => true, 'document_number' => $purchaseOrder->document_number], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;

    }
}
