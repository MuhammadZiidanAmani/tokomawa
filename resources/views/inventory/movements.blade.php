<x-layouts.app title="Mutasi Stok">
    <div class="grid gap-6">
        <x-page-header title="Riwayat Mutasi Stok" description="Ledger immutable berdasarkan barang, gudang, batch, dan dokumen sumber." />

        <form method="GET" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1"><x-ui.input name="search" label="Cari mutasi" :value="request('search')" placeholder="SKU, nama, atau ID sumber" /></div>
            <x-ui.button type="submit">Cari</x-ui.button>
        </form>

        <x-ui.card>
            @if ($movements->isEmpty())
                <div class="py-10 text-center text-sm text-slate-500">Belum ada mutasi stok.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500"><tr><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Gudang</th><th class="px-3 py-2">Jenis</th><th class="px-3 py-2 text-right">Perubahan</th><th class="px-3 py-2 text-right">Saldo</th><th class="px-3 py-2 text-right">HPP snapshot</th><th class="px-3 py-2">Sumber</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-3">{{ $movement->posted_at->timezone(config('retail.display_timezone'))->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $movement->product->sku }}</span><br>{{ $movement->product->name }}</td>
                                    <td class="px-3 py-3">{{ $movement->warehouse->name }}</td>
                                    <td class="px-3 py-3">{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                                    <td @class(['px-3 py-3 text-right font-semibold tabular-nums', 'text-primary-700' => $movement->direction === 'in', 'text-danger-600' => $movement->direction === 'out'])>{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($movement->qty_delta) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($movement->qty_after) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->currencyDecimal($movement->unit_cost_snapshot) }}</td>
                                    <td class="px-3 py-3 font-mono text-xs">{{ $movement->source_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $movements->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
