<x-layouts.app title="Penerimaan Barang">
    <div class="grid gap-6">
        <x-page-header title="Penerimaan Barang" description="Terima barang dari Purchase Order secara parsial; stok hanya berubah saat dokumen diposting.">
            <x-slot:actions>
                @can('create', App\Domain\Purchasing\Models\GoodsReceipt::class)
                    <x-ui.button :href="route('purchasing.goods-receipts.create')">Buat Penerimaan</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif

        <x-ui.card title="Pencarian dan filter" description="Pencarian mencakup nomor GR, PO, surat jalan, dan supplier.">
            <form method="GET" action="{{ route('purchasing.goods-receipts.index') }}" class="grid gap-4 lg:grid-cols-4">
                <x-ui.input name="search" label="Cari dokumen" :value="request('search')" placeholder="GR, PO, surat jalan, supplier..." />
                <x-ui.select name="warehouse_id" label="Gudang">
                    <option value="">Semua gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="status" label="Status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit">Terapkan</x-ui.button>
                    <x-ui.button :href="route('purchasing.goods-receipts.index')" variant="secondary">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($receipts->isEmpty())
            <x-ui.card>
                <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
                    <p class="font-semibold text-slate-800">Belum ada penerimaan yang sesuai.</p>
                    <p class="mt-1 text-sm text-slate-500">Pilih Purchase Order approved/sent untuk membuat draft penerimaan.</p>
                </div>
            </x-ui.card>
        @else
            <x-ui.data-table label="Daftar Penerimaan Barang">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Nomor / Waktu</th><th class="px-4 py-3">PO / Supplier</th><th class="px-4 py-3">Gudang</th><th class="px-4 py-3 text-right">Qty dasar</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($receipts as $receipt)
                        @php $variant = match ($receipt->status) { App\Domain\Purchasing\Enums\GoodsReceiptStatus::Posted => 'success', App\Domain\Purchasing\Enums\GoodsReceiptStatus::Cancelled => 'danger', default => 'neutral' }; @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3"><p class="font-mono text-xs font-semibold text-slate-950">{{ $receipt->document_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $receipt->received_at->timezone(config('retail.display_timezone'))->translatedFormat('d M Y, H:i') }}</p><p class="text-xs text-slate-500">SJ: {{ $receipt->supplier_delivery_note }}</p></td>
                            <td class="px-4 py-3"><p class="font-mono text-xs font-semibold">{{ $receipt->purchaseOrder->document_number }}</p><p class="font-semibold text-slate-900">{{ $receipt->supplier_name_snapshot }}</p></td>
                            <td class="px-4 py-3">{{ $receipt->warehouse->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ app(App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity($receipt->total_base_quantity) }}<p class="text-xs text-slate-500">{{ $receipt->items_count }} item</p></td>
                            <td class="px-4 py-3"><x-ui.badge :variant="$variant">{{ $receipt->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('purchasing.goods-receipts.show', $receipt) }}" class="font-semibold text-primary-700 hover:underline">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.data-table>
            <div>{{ $receipts->links() }}</div>
        @endif
    </div>
</x-layouts.app>
