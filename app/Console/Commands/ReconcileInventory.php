<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Services\InventoryReconciliationService;
use App\Domain\Organization\Models\Branch;
use Illuminate\Console\Command;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {branch : Public ID cabang}';

    protected $description = 'Bandingkan proyeksi saldo persediaan dengan ledger mutasi';

    public function handle(InventoryReconciliationService $reconciliation): int
    {
        $branch = Branch::query()->where('public_id', $this->argument('branch'))->firstOrFail();
        $results = $reconciliation->reconcile($branch);
        $differences = $results->where('is_reconciled', false);

        $this->table(
            ['Gudang', 'SKU', 'Saldo', 'Ledger', 'Selisih'],
            $differences->map(fn (array $row): array => [
                $row['warehouse_name'], $row['product_sku'], $row['balance_quantity'],
                $row['ledger_quantity'], $row['quantity_difference'],
            ])->all(),
        );

        if ($differences->isNotEmpty()) {
            $this->error('Ditemukan perbedaan saldo persediaan.');

            return self::FAILURE;
        }

        $this->info('Ledger dan saldo persediaan sesuai.');

        return self::SUCCESS;
    }
}
