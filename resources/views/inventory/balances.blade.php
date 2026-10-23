<x-layouts.app title="Saldo Stok">
    <div class="grid gap-6">
        <x-page-header title="Saldo Stok per Barang" description="Proyeksi saldo yang diperbarui atomik bersama ledger mutasi." />

        <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_16rem_auto]">
            <x-ui.input name="search" label="Cari barang" :value="request('search')" placeholder="SKU atau nama barang" />
            <x-ui.select name="warehouse_id" label="Gudang">
                <option value="">Semua gudang</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </x-ui.select>
            <div class="flex items-end"><x-ui.button type="submit">Terapkan</x-ui.button></div>
        </form>

        <x-ui.card>
            @if ($balances->isEmpty())
                <div class="py-10 text-center text-sm text-slate-500">Belum ada saldo yang sesuai filter.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500"><tr><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Gudang</th><th class="px-3 py-2">Batch/Status</th><th class="px-3 py-2 text-right">On hand</th><th class="px-3 py-2 text-right">Reserved</th><th class="px-3 py-2 text-right">HPP rata-rata</th><th class="px-3 py-2 text-right">Nilai</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($balances as $balance)
                                <tr>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $balance->product->sku }}</span><br><span class="text-slate-500">{{ $balance->product->name }}</span></td>
                                    <td class="px-3 py-3"><a class="font-medium text-primary-700" href="{{ route('inventory.warehouses.show', $balance->warehouse) }}">{{ $balance->warehouse->name }}</a></td>
                                    <td class="px-3 py-3">{{ $balance->batch?->lot_number ?? 'Tanpa batch' }}<br><span class="text-xs text-slate-500">{{ $balance->stock_status }}</span></td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($balance->qty_on_hand) }} {{ $balance->product->baseUnit->symbol }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($balance->qty_reserved) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->currencyDecimal($balance->avg_unit_cost) }}</td>
                                    <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->currencyDecimal($balance->inventory_value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $balances->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
