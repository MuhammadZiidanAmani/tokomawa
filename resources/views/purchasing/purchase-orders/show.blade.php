@php
    $formatter = app(App\Domain\Shared\Support\IndonesiaFormatter::class);
    $variant = match ($purchaseOrder->status) {
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::Approved,
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::Completed => 'success',
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::PendingApproval,
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::Sent,
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::PartiallyReceived => 'warning',
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::Rejected,
        App\Domain\Purchasing\Enums\PurchaseOrderStatus::Cancelled => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.app title="Detail Purchase Order">
    <div class="grid gap-6">
        <x-page-header :title="$purchaseOrder->document_number" :description="'Pesanan untuk '.$purchaseOrder->supplier_name_snapshot">
            <x-slot:actions>
                <x-ui.button :href="route('purchasing.purchase-orders.index')" variant="secondary">Kembali</x-ui.button>
                @can('update', $purchaseOrder)<x-ui.button :href="route('purchasing.purchase-orders.edit', $purchaseOrder)">Edit Draft</x-ui.button>@endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert> @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <div class="grid gap-6">
                <x-ui.card title="Ringkasan Dokumen">
                    <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-2"><x-ui.badge :variant="$variant">{{ $purchaseOrder->status->label() }}</x-ui.badge></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal PO</dt><dd class="mt-2 font-semibold">{{ $purchaseOrder->order_date->translatedFormat('d F Y') }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimasi Datang</dt><dd class="mt-2 font-semibold">{{ $purchaseOrder->expected_at?->translatedFormat('d F Y') ?? 'Belum ditentukan' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</dt><dd class="mt-2 text-lg font-bold tabular-nums">{{ $formatter->currencyDecimal($purchaseOrder->grand_total) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supplier (snapshot)</dt><dd class="mt-2 font-semibold">{{ $purchaseOrder->supplier_code_snapshot }} — {{ $purchaseOrder->supplier_name_snapshot }}</dd><dd class="text-sm text-slate-500">{{ $purchaseOrder->supplier_address_snapshot ?: 'Alamat tidak tersedia' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</dt><dd class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $purchaseOrder->notes ?: 'Tidak ada catatan.' }}</dd></div>
                    </dl>
                </x-ui.card>

                <x-ui.card title="Item Pesanan" description="Snapshot berikut tidak berubah saat master barang atau harga supplier diperbarui.">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Satuan</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Harga</th><th class="px-3 py-2 text-right">Subtotal</th><th class="px-3 py-2 text-right">Outstanding dasar</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($purchaseOrder->items as $item)
                                    <tr>
                                        <td class="px-3 py-3"><p class="font-mono text-xs font-semibold">{{ $item->product_sku_snapshot }}</p><p class="font-semibold text-slate-900">{{ $item->product_name_snapshot }}</p>@if ($item->supplier_sku_snapshot)<p class="text-xs text-slate-500">SKU supplier: {{ $item->supplier_sku_snapshot }}</p>@endif</td>
                                        <td class="px-3 py-3">{{ $item->unit_name_snapshot }} ({{ $item->unit_symbol_snapshot }})<br><span class="text-xs text-slate-500">Konversi × {{ $formatter->quantity($item->conversion_factor, 6) }}</span></td>
                                        <td class="px-3 py-3 text-right tabular-nums">{{ $formatter->quantity($item->quantity) }}</td>
                                        <td class="px-3 py-3 text-right tabular-nums">{{ $formatter->currencyDecimal($item->unit_price) }}</td>
                                        <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $formatter->currencyDecimal($item->subtotal) }}</td>
                                        <td class="px-3 py-3 text-right tabular-nums">{{ $formatter->quantity($item->outstandingBaseQuantity()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="border-t-2 border-slate-200"><th colspan="4" class="px-3 py-4 text-right">Total</th><th class="px-3 py-4 text-right text-lg tabular-nums">{{ $formatter->currencyDecimal($purchaseOrder->grand_total) }}</th><th></th></tr></tfoot>
                        </table>
                    </div>
                </x-ui.card>

                <x-ui.card title="Riwayat Status & Audit" description="Catatan ini append-only dan menyimpan aktor serta alasan.">
                    @if ($auditLogs->isEmpty())
                        <p class="py-6 text-center text-sm text-slate-500">Belum ada riwayat audit.</p>
                    @else
                        <ol class="grid gap-4">
                            @foreach ($auditLogs as $log)
                                <li class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-semibold text-slate-900">{{ str($log->action)->replace(['purchase_order.', '_'], ['', ' '])->headline() }}</p><p class="text-sm text-slate-500">{{ $log->actor?->name ?? 'Sistem' }} · {{ implode(', ', $log->role_snapshot ?? []) ?: 'tanpa role' }}</p></div><time class="text-xs text-slate-500">{{ $formatter->dateTime($log->created_at) }}</time></div><p class="mt-2 text-sm text-slate-700">{{ $log->reason }}</p></li>
                            @endforeach
                        </ol>
                    @endif
                </x-ui.card>
            </div>

            <aside class="grid content-start gap-6">
                @can('submit', $purchaseOrder)
                    <x-ui.card title="Ajukan Persetujuan" description="Nilai PO menjadi dasar evaluasi batas approval yang dikonfigurasi.">
                        <form method="POST" action="{{ route('purchasing.purchase-orders.submit', $purchaseOrder) }}" class="grid gap-3" x-data="{ loading: false }" x-on:submit="loading = true">
                            @csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                            <textarea name="reason" rows="3" required minlength="10" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Alasan pengajuan (minimal 10 karakter)"></textarea>
                            <x-ui.button type="submit" x-bind:disabled="loading">Ajukan</x-ui.button>
                        </form>
                    </x-ui.card>
                @endcan

                @can('approve', $purchaseOrder)
                    <x-ui.card title="Keputusan Persetujuan" description="Pembuat PO tidak dapat memutuskan dokumennya sendiri. DEC-007 tetap mengikuti konfigurasi approval limit.">
                        <form method="POST" class="grid gap-3">
                            @csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                            <textarea name="decision_notes" rows="3" required minlength="10" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Catatan keputusan (minimal 10 karakter)"></textarea>
                            <div class="grid grid-cols-2 gap-2"><x-ui.button type="submit" formaction="{{ route('purchasing.purchase-orders.approve', $purchaseOrder) }}">Setujui</x-ui.button><x-ui.button type="submit" formaction="{{ route('purchasing.purchase-orders.reject', $purchaseOrder) }}" variant="danger">Tolak</x-ui.button></div>
                        </form>
                    </x-ui.card>
                @endcan

                @can('send', $purchaseOrder)
                    <x-ui.card title="Penerbitan PO" description="Menandai PO telah dikirim tidak mengubah stok maupun hutang.">
                        <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><x-ui.button type="submit" class="w-full">Tandai Sudah Dikirim</x-ui.button></form>
                    </x-ui.card>
                @endcan

                @can('cancel', $purchaseOrder)
                    <x-ui.card title="Batalkan Purchase Order">
                        <form method="POST" action="{{ route('purchasing.purchase-orders.cancel', $purchaseOrder) }}" class="grid gap-3">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><textarea name="reason" rows="3" required minlength="10" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Alasan pembatalan (minimal 10 karakter)"></textarea><x-ui.button type="submit" variant="danger">Batalkan PO</x-ui.button></form>
                    </x-ui.card>
                @endcan

                <x-ui.alert variant="info">Purchase Order tidak membuat inventory movement, inventory balance, hutang, pembayaran, atau invoice.</x-ui.alert>
            </aside>
        </div>
    </div>
</x-layouts.app>
