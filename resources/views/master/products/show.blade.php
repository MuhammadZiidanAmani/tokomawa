<x-layouts.app :title="$product->name">
    <div class="grid gap-6">
        <x-page-header :title="$product->name" :description="$product->sku.' · '.$product->classification->label()">
            <x-slot:actions>
                <x-ui.button :href="route('master.products.index')" variant="secondary">Daftar barang</x-ui.button>
                @can('update', $product)<x-ui.button :href="route('master.products.edit', $product)">Edit barang</x-ui.button>@endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card title="Status"><x-ui.badge :variant="$product->is_active ? 'success' : 'neutral'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge></x-ui.card>
            <x-ui.card title="Kategori"><p class="font-semibold">{{ $product->category?->name ?? 'Belum ditetapkan' }}</p><p class="text-xs text-slate-500">{{ $product->department?->name ?? 'Tanpa departemen' }} / {{ $product->subcategory?->name ?? 'Tanpa subkategori' }}</p></x-ui.card>
            <x-ui.card title="Satuan dasar"><p class="font-semibold">{{ $product->baseUnit->name }} ({{ $product->baseUnit->symbol }})</p></x-ui.card>
            <x-ui.card title="Supplier utama"><p class="font-semibold">{{ $product->defaultSupplier?->name ?? 'Belum ditetapkan' }}</p></x-ui.card>
        </div>

        <section class="grid gap-6 xl:grid-cols-2">
            <x-ui.card title="Barcode & satuan">
                <div class="grid gap-5">
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Barcode</p><div class="mt-2 flex flex-wrap gap-2">@forelse ($product->barcodes as $barcode)<x-ui.badge :variant="$barcode->is_primary ? 'success' : 'neutral'">{{ $barcode->barcode }}{{ $barcode->is_primary ? ' · utama' : '' }}</x-ui.badge>@empty<span class="text-slate-500">Belum ada barcode.</span>@endforelse</div></div>
                    <x-ui.data-table label="Konversi satuan">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">Satuan</th><th class="px-4 py-3 text-right">Faktor</th><th class="px-4 py-3">Penggunaan</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">@foreach ($product->units as $productUnit)<tr><td class="px-4 py-3">{{ $productUnit->unit->name }}</td><td class="px-4 py-3 text-right font-mono">{{ $productUnit->conversion_factor }}</td><td class="px-4 py-3 text-slate-600">{{ collect([$productUnit->is_purchase_unit ? 'Pembelian' : null, $productUnit->is_sale_unit ? 'Penjualan' : null])->filter()->join(', ') ?: 'Dasar' }}</td></tr>@endforeach</tbody>
                    </x-ui.data-table>
                </div>
            </x-ui.card>

            <x-ui.card title="Pajak & kontrol">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Aturan pajak</dt><dd class="mt-1 font-medium">{{ $product->taxRule?->name ?? 'Tidak diklasifikasikan' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Stok minimum</dt><dd class="mt-1 font-mono font-medium">{{ $product->min_stock }} {{ $product->baseUnit->symbol }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Batch / expiry</dt><dd class="mt-1">{{ $product->track_batch ? 'Batch aktif' : 'Tanpa batch' }} · {{ $product->track_expiry ? 'Expiry aktif' : 'Tanpa expiry' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Qty pecahan</dt><dd class="mt-1">{{ $product->allow_fractional_qty ? 'Diizinkan' : 'Tidak diizinkan' }}</dd></div>
                </dl>
                <p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Halaman ini hanya menampilkan konfigurasi master. Tidak ada saldo stok yang dapat diedit.</p>
            </x-ui.card>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <x-ui.card title="Supplier terkait">
                <div class="grid gap-3">@forelse ($product->supplierProducts as $relation)<div class="rounded-lg border border-slate-200 p-3"><p class="font-semibold">{{ $relation->supplier->name }}</p><p class="text-xs text-slate-500">{{ $relation->supplier_sku ?? 'Tanpa kode supplier' }} · MOQ {{ $relation->minimum_order_qty }}</p></div>@empty<p class="text-slate-500">Belum ada supplier terkait.</p>@endforelse</div>
            </x-ui.card>
            <x-ui.card title="Rak & lokasi">
                <div class="grid gap-3">@forelse ($product->locations as $assignment)<div class="rounded-lg border border-slate-200 p-3"><p class="font-semibold">{{ $assignment->shelfLocation->code }} · {{ $assignment->shelfLocation->name }}</p><p class="text-xs text-slate-500">{{ $assignment->shelfLocation->warehouse->name }}</p></div>@empty<p class="text-slate-500">Belum ditempatkan pada rak.</p>@endforelse</div>
            </x-ui.card>
            <x-ui.card title="Harga efektif">
                <div class="grid gap-3">@forelse ($product->prices as $price)<div class="rounded-lg border border-slate-200 p-3"><p class="font-semibold">{{ $price->priceList->name }} · {{ $formatter->currencyDecimal($price->amount) }}</p><p class="text-xs text-slate-500">{{ $price->unit->symbol }} · mulai {{ $formatter->dateTime($price->effective_from) }}</p></div>@empty<p class="text-slate-500">Belum ada harga efektif.</p>@endforelse</div>
            </x-ui.card>
        </section>
    </div>
</x-layouts.app>
