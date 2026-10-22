<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organization\Models\Branch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReconciliationService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcile(Branch $branch): Collection
    {
        $ledger = DB::table('inventory_movements')
            ->select(['warehouse_id', 'product_id', 'batch_scope', 'stock_status'])
            ->selectRaw('SUM(qty_delta) AS ledger_quantity')
            ->selectRaw('SUM(value_delta) AS ledger_value')
            ->where('branch_id', $branch->getKey())
            ->groupBy(['warehouse_id', 'product_id', 'batch_scope', 'stock_status'])
            ->get()
            ->keyBy(fn (object $row): string => $this->key($row->warehouse_id, $row->product_id, $row->batch_scope, $row->stock_status));

        $balances = InventoryBalance::query()
            ->with(['warehouse:id,name', 'product:id,sku,name'])
            ->whereBelongsTo($branch)
            ->get();

        $results = collect();
        foreach ($balances as $balance) {
            $key = $this->key($balance->warehouse_id, $balance->product_id, $balance->batch_scope, $balance->stock_status);
            $movement = $ledger->pull($key);
            $ledgerQuantity = bcadd((string) ($movement->ledger_quantity ?? '0'), '0', 3);
            $ledgerValue = bcadd((string) ($movement->ledger_value ?? '0'), '0', 6);
            $results->push($this->result($balance, $ledgerQuantity, $ledgerValue));
        }

        foreach ($ledger as $movement) {
            $results->push([
                'warehouse_id' => $movement->warehouse_id,
                'warehouse_name' => 'Tidak ditemukan',
                'product_id' => $movement->product_id,
                'product_sku' => 'Tidak ditemukan',
                'product_name' => 'Tidak ditemukan',
                'batch_scope' => $movement->batch_scope,
                'stock_status' => $movement->stock_status,
                'balance_quantity' => '0.000',
                'ledger_quantity' => bcadd((string) $movement->ledger_quantity, '0', 3),
                'quantity_difference' => bcsub('0', (string) $movement->ledger_quantity, 3),
                'balance_value' => '0.000000',
                'ledger_value' => bcadd((string) $movement->ledger_value, '0', 6),
                'value_difference' => bcsub('0', (string) $movement->ledger_value, 6),
                'is_reconciled' => false,
            ]);
        }

        return $results->values();
    }

    /** @return array<string, mixed> */
    private function result(InventoryBalance $balance, string $ledgerQuantity, string $ledgerValue): array
    {
        $quantityDifference = bcsub($balance->qty_on_hand, $ledgerQuantity, 3);
        $valueDifference = bcsub($balance->inventory_value, $ledgerValue, 6);

        return [
            'warehouse_id' => $balance->warehouse_id,
            'warehouse_name' => $balance->warehouse->name,
            'product_id' => $balance->product_id,
            'product_sku' => $balance->product->sku,
            'product_name' => $balance->product->name,
            'batch_scope' => $balance->batch_scope,
            'stock_status' => $balance->stock_status,
            'balance_quantity' => $balance->qty_on_hand,
            'ledger_quantity' => $ledgerQuantity,
            'quantity_difference' => $quantityDifference,
            'balance_value' => $balance->inventory_value,
            'ledger_value' => $ledgerValue,
            'value_difference' => $valueDifference,
            'is_reconciled' => bccomp($quantityDifference, '0', 3) === 0 && bccomp($valueDifference, '0', 6) === 0,
        ];
    }

    private function key(int $warehouseId, int $productId, string $batchScope, string $stockStatus): string
    {
        return implode(':', [$warehouseId, $productId, $batchScope, $stockStatus]);
    }
}
