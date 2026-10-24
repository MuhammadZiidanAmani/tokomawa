@php($formatter = app(App\Domain\Shared\Support\IndonesiaFormatter::class))
<x-layouts.app title="Transaksi {{ $sale->document_number }}">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Penjualan berhasil diposting</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-950">{{ $sale->document_number }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $sale->sold_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · {{ $sale->register->name }} · {{ $sale->cashier->name }}</p>
            </div>
            <x-ui.button href="{{ route('pos.index') }}">Transaksi Baru</x-ui.button>
        </div>

        @if (session('status')) <x-ui.alert variant="success" title="Berhasil">{{ session('status') }}</x-ui.alert> @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-3">Barang</th><th class="py-3 text-right">Qty</th><th class="py-3 text-right">Harga</th><th class="py-3 text-right">Total</th><th class="py-3 text-right">HPP</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sale->items as $item)
                                <tr><td class="py-3"><p class="font-semibold">{{ $item->product_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $item->product_sku_snapshot }}@if($item->batch_lot_snapshot) · Batch {{ $item->batch_lot_snapshot }}@endif</p></td><td class="py-3 text-right tabular-nums">{{ $formatter->quantity($item->quantity) }} {{ $item->unit_symbol_snapshot }}</td><td class="py-3 text-right tabular-nums">{{ $formatter->currencyDecimal($item->unit_price_snapshot) }}</td><td class="py-3 text-right font-semibold tabular-nums">{{ $formatter->currencyDecimal($item->line_total) }}</td><td class="py-3 text-right tabular-nums">{{ $formatter->currencyDecimal($item->cogs_total_snapshot) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <div class="space-y-4">
                <x-ui.card>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd class="text-xl font-bold">{{ $formatter->currencyDecimal($sale->grand_total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Tunai diterima</dt><dd class="font-semibold">{{ $formatter->currencyDecimal($sale->cash_tendered) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3"><dt class="text-slate-500">Kembalian</dt><dd class="font-bold text-primary-700">{{ $formatter->currencyDecimal($sale->change_due) }}</dd></div>
                    </dl>
                </x-ui.card>
                <x-ui.alert variant="info" title="Bukti transaksi minimum">Cetak/reprint nota final dan penutupan shift tersedia pada M1.6. Penjualan, pembayaran, ledger kas, stok, serta HPP sudah tersimpan.</x-ui.alert>
            </div>
        </div>
    </div>
</x-layouts.app>
