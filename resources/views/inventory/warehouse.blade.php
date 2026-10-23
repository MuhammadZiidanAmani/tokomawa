<x-layouts.app :title="'Stok '.$warehouse->name">
    <div class="grid gap-6">
        <x-page-header :title="'Detail Stok · '.$warehouse->name" :description="'Gudang '.$warehouse->code.' pada cabang aktif.'">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('inventory.balances')">Kembali ke saldo</x-ui.button></x-slot:actions>
        </x-page-header>

        <x-ui.card>
            @if ($balances->isEmpty())
                <div class="py-10 text-center text-sm text-slate-500">Gudang ini belum memiliki saldo stok.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500"><tr><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Batch</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">On hand</th><th class="px-3 py-2 text-right">Tersedia</th><th class="px-3 py-2 text-right">HPP rata-rata</th><th class="px-3 py-2 text-right">Nilai</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($balances as $balance)
                                <tr>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $balance->product->sku }}</span><br><span class="text-slate-500">{{ $balance->product->name }}</span></td>
                                    <td class="px-3 py-3">{{ $balance->batch?->lot_number ?? 'Tanpa batch' }}</td>
                                    <td class="px-3 py-3">{{ str($balance->stock_status)->title() }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($balance->qty_on_hand) }}</td>
                                    <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity(bcsub($balance->qty_on_hand, $balance->qty_reserved, 3)) }}</td>
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
