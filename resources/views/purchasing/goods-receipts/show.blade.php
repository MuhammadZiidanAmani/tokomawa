@php
    $formatter = app(App\Domain\Shared\Support\IndonesiaFormatter::class);
    $variant = match ($goodsReceipt->status) { App\Domain\Purchasing\Enums\GoodsReceiptStatus::Posted => 'success', App\Domain\Purchasing\Enums\GoodsReceiptStatus::Cancelled => 'danger', default => 'neutral' };
@endphp

<x-layouts.app title="Detail Penerimaan Barang">
    <div class="grid gap-6">
        <x-page-header :title="$goodsReceipt->document_number" :description="'Penerimaan dari '.$goodsReceipt->supplier_name_snapshot">
            <x-slot:actions><x-ui.button :href="route('purchasing.goods-receipts.index')" variant="secondary">Kembali</x-ui.button>@can('update', $goodsReceipt)<x-ui.button :href="route('purchasing.goods-receipts.edit', $goodsReceipt)">Edit Draft</x-ui.button>@endcan</x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert> @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <div class="grid gap-6">
                <x-ui.card title="Ringkasan Dokumen">
                    <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-2"><x-ui.badge :variant="$variant">{{ $goodsReceipt->status->label() }}</x-ui.badge></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purchase Order</dt><dd class="mt-2"><a href="{{ route('purchasing.purchase-orders.show', $goodsReceipt->purchaseOrder) }}" class="font-mono text-sm font-semibold text-primary-700 hover:underline">{{ $goodsReceipt->purchaseOrder->document_number }}</a></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gudang</dt><dd class="mt-2 font-semibold">{{ $goodsReceipt->warehouse->name }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diterima</dt><dd class="mt-2 font-semibold">{{ $formatter->dateTime($goodsReceipt->received_at) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supplier / Surat Jalan</dt><dd class="mt-2 font-semibold">{{ $goodsReceipt->supplier_code_snapshot }} — {{ $goodsReceipt->supplier_name_snapshot }}</dd><dd class="text-sm text-slate-500">{{ $goodsReceipt->supplier_delivery_note }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Qty Dasar</dt><dd class="mt-2 text-lg font-bold tabular-nums">{{ $formatter->quantity($goodsReceipt->total_base_quantity) }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nilai Snapshot</dt><dd class="mt-2 text-lg font-bold tabular-nums">{{ $formatter->currencyDecimal($goodsReceipt->total_value) }}</dd></div>
                    </dl>
                </x-ui.card>

                <x-ui.card title="Item Penerimaan" description="Snapshot satuan, biaya, batch, dan kuantitas tidak berubah setelah posting.">
                    <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Batch</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Qty dasar</th><th class="px-3 py-2 text-right">Biaya dasar</th><th class="px-3 py-2 text-right">Nilai</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach ($goodsReceipt->items as $item)<tr><td class="px-3 py-3"><p class="font-mono text-xs font-semibold">{{ $item->product_sku_snapshot }}</p><p class="font-semibold">{{ $item->product_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $item->unit_name_snapshot }} × {{ $formatter->quantity($item->conversion_factor, 6) }}</p></td><td class="px-3 py-3">{{ $item->lot_number_snapshot ?: 'Tidak dilacak' }}@if($item->expires_at_snapshot)<p class="text-xs text-slate-500">Exp {{ $item->expires_at_snapshot->translatedFormat('d M Y') }}</p>@endif</td><td class="px-3 py-3 text-right tabular-nums">{{ $formatter->quantity($item->quantity) }} {{ $item->unit_symbol_snapshot }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $formatter->quantity($item->base_quantity) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $formatter->currencyDecimal($item->base_unit_cost_snapshot) }}</td><td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $formatter->currencyDecimal($item->line_total) }}</td></tr>@endforeach
                    </tbody></table></div>
                </x-ui.card>

                @if ($goodsReceipt->status === App\Domain\Purchasing\Enums\GoodsReceiptStatus::Posted)
                    <x-ui.card title="Efek Ledger Stok" description="Movement immutable berikut dibuat atomik bersama update outstanding PO.">
                        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Gudang</th><th class="px-3 py-2 text-right">Masuk</th><th class="px-3 py-2 text-right">Saldo Setelah</th><th class="px-3 py-2 text-right">HPP Snapshot</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($movements as $movement)<tr><td class="px-3 py-3 font-semibold">{{ $movement->product->name }}</td><td class="px-3 py-3">{{ $movement->warehouse->name }}</td><td class="px-3 py-3 text-right tabular-nums">+{{ $formatter->quantity($movement->qty_delta) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $formatter->quantity($movement->qty_after) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $formatter->currencyDecimal($movement->unit_cost_snapshot) }}</td></tr>@endforeach</tbody></table></div>
                    </x-ui.card>
                @endif

                <x-ui.card title="Riwayat Audit" description="Perubahan draft, posting, dan pembatalan dicatat append-only.">
                    @if($auditLogs->isEmpty())<p class="py-6 text-center text-sm text-slate-500">Belum ada riwayat audit.</p>@else<ol class="grid gap-4">@foreach($auditLogs as $log)<li class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-2"><div><p class="font-semibold">{{ str($log->action)->replace(['goods_receipt.', '_'], ['', ' '])->headline() }}</p><p class="text-sm text-slate-500">{{ $log->actor?->name ?? 'Sistem' }}</p></div><time class="text-xs text-slate-500">{{ $formatter->dateTime($log->created_at) }}</time></div><p class="mt-2 text-sm text-slate-700">{{ $log->reason }}</p></li>@endforeach</ol>@endif
                </x-ui.card>
            </div>

            <aside class="grid content-start gap-6">
                @can('post', $goodsReceipt)
                    <x-ui.card title="Posting Penerimaan" description="Posting menambah stok, menghitung MWA, dan mengurangi outstanding PO. Dokumen tidak dapat diedit setelah commit.">
                        <form method="POST" action="{{ route('purchasing.goods-receipts.post', $goodsReceipt) }}" x-data="{ loading: false }" x-on:submit="loading = true" class="grid gap-3">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><x-ui.button type="submit" x-bind:disabled="loading"><span x-show="!loading">Posting Penerimaan</span><span x-show="loading" x-cloak>Memposting...</span></x-ui.button></form>
                    </x-ui.card>
                @endcan
                @can('cancel', $goodsReceipt)
                    <x-ui.card title="Batalkan Draft"><form method="POST" action="{{ route('purchasing.goods-receipts.cancel', $goodsReceipt) }}" class="grid gap-3">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><textarea name="reason" rows="3" required minlength="10" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Alasan pembatalan (minimal 10 karakter)"></textarea><x-ui.button type="submit" variant="danger">Batalkan Draft</x-ui.button></form></x-ui.card>
                @endcan
                <x-ui.alert variant="info">Penerimaan barang tidak membuat invoice, hutang supplier, atau pembayaran.</x-ui.alert>
            </aside>
        </div>
    </div>
</x-layouts.app>
