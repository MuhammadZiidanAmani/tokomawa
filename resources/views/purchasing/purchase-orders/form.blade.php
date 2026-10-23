@php
    $editing = $purchaseOrder !== null;
    $initialItems = old('items', $editing
        ? $purchaseOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'unit_id' => $item->unit_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'search' => '',
        ])->values()->all()
        : [['product_id' => '', 'unit_id' => '', 'quantity' => '1.000', 'unit_price' => '0.00', 'search' => '']]);
    $selectedSupplier = (string) old('supplier_id', $purchaseOrder?->supplier_id ?? '');
@endphp

<x-layouts.app :title="$editing ? 'Edit Purchase Order' : 'Buat Purchase Order'">
    <form
        method="POST"
        action="{{ $editing ? route('purchasing.purchase-orders.update', $purchaseOrder) : route('purchasing.purchase-orders.store') }}"
        class="grid gap-6"
        x-data="{
            products: {{ Illuminate\Support\Js::from($products) }},
            items: {{ Illuminate\Support\Js::from($initialItems) }},
            supplierId: {{ Illuminate\Support\Js::from($selectedSupplier) }},
            submitting: false,
            product(item) { return this.products.find(product => String(product.id) === String(item.product_id)); },
            filteredProducts(item) {
                const query = String(item.search || '').toLocaleLowerCase('id-ID').trim();
                const matches = this.products.filter(product => !query || `${product.sku} ${product.name}`.toLocaleLowerCase('id-ID').includes(query));
                const selected = this.product(item);
                if (selected && !matches.some(product => String(product.id) === String(selected.id))) matches.unshift(selected);
                return matches.slice(0, 50);
            },
            units(item) { return this.product(item)?.units ?? []; },
            refresh(item) {
                const product = this.product(item);
                if (!product) { item.unit_id = ''; return; }
                const suggestion = product.supplier_options?.[String(this.supplierId)];
                item.unit_id = suggestion?.unit_id ?? product.units?.[0]?.id ?? '';
                if (suggestion?.price !== null && suggestion?.price !== undefined) item.unit_price = suggestion.price;
            },
            addItem() { this.items.push({ product_id: '', unit_id: '', quantity: '1.000', unit_price: '0.00', search: '' }); },
            removeItem(index) { if (this.items.length > 1) this.items.splice(index, 1); },
            lineTotal(item) { return (Number(item.quantity || 0) * Number(item.unit_price || 0)).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            total() { return this.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        }"
        x-on:submit="submitting = true"
    >
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $formKey) }}">

        <x-page-header :title="$editing ? 'Edit Draft Purchase Order' : 'Buat Purchase Order'" description="Nilai dihitung ulang secara presisi di server. Penyimpanan draft tidak memengaruhi stok atau keuangan.">
            <x-slot:actions>
                <x-ui.button :href="$editing ? route('purchasing.purchase-orders.show', $purchaseOrder) : route('purchasing.purchase-orders.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button type="submit" x-bind:disabled="submitting"><span x-show="!submitting">Simpan Draft</span><span x-show="submitting" x-cloak>Menyimpan...</span></x-ui.button>
            </x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <x-ui.alert variant="danger">
                <p class="font-semibold">Draft belum dapat disimpan.</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-ui.alert>
        @endif

        <x-ui.card title="Informasi Pesanan" description="Nomor PO dialokasikan otomatis dan aman terhadap concurrency.">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.select name="supplier_id" label="Supplier" required x-model="supplierId">
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($selectedSupplier === (string) $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="order_date" type="date" label="Tanggal PO" :value="old('order_date', $purchaseOrder?->order_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                <x-ui.input name="expected_at" type="date" label="Estimasi Kedatangan" :value="old('expected_at', $purchaseOrder?->expected_at?->format('Y-m-d'))" />
                @if ($editing)
                    <div class="grid gap-1.5"><span class="text-sm font-semibold text-slate-700">Nomor PO</span><div class="min-h-10 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm">{{ $purchaseOrder->document_number }}</div></div>
                @endif
            </div>
            <div class="mt-4 grid gap-1.5">
                <label for="notes" class="text-sm font-semibold text-slate-700">Catatan</label>
                <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 shadow-sm focus:border-primary-600">{{ old('notes', $purchaseOrder?->notes) }}</textarea>
            </div>
        </x-ui.card>

        <x-ui.card title="Item Purchase Order" description="Satuan yang tampil hanya satuan dasar atau satuan pembelian aktif.">
            <div class="grid gap-4" aria-live="polite">
                <template x-for="(item, index) in items" :key="index">
                    <section class="grid gap-3 rounded-xl border border-slate-200 p-4 lg:grid-cols-[minmax(14rem,2fr)_minmax(11rem,1fr)_9rem_11rem_10rem_auto] lg:items-end">
                        <div class="grid gap-1.5">
                            <label class="text-sm font-semibold text-slate-700" x-bind:for="`product-${index}`">Barang</label>
                            <input type="search" x-model.debounce.150ms="item.search" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Cari SKU atau nama...">
                            <select x-bind:id="`product-${index}`" x-bind:name="`items[${index}][product_id]`" x-model="item.product_id" x-on:change="refresh(item)" required class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Pilih barang</option>
                                <template x-for="productOption in filteredProducts(item)" :key="productOption.id"><option x-bind:value="productOption.id" x-text="`${productOption.sku} — ${productOption.name}`"></option></template>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-sm font-semibold text-slate-700" x-bind:for="`unit-${index}`">Satuan</label>
                            <select x-bind:id="`unit-${index}`" x-bind:name="`items[${index}][unit_id]`" x-model="item.unit_id" required class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Pilih satuan</option>
                                <template x-for="unit in units(item)" :key="unit.id"><option x-bind:value="unit.id" x-text="`${unit.label} × ${unit.factor}`"></option></template>
                            </select>
                        </div>
                        <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`quantity-${index}`">Kuantitas</label><input x-bind:id="`quantity-${index}`" x-bind:name="`items[${index}][quantity]`" x-model="item.quantity" type="number" min="0.001" step="0.001" required class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums"></div>
                        <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`price-${index}`">Harga beli</label><input x-bind:id="`price-${index}`" x-bind:name="`items[${index}][unit_price]`" x-model="item.unit_price" type="number" min="0" step="0.01" required class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums"></div>
                        <div class="grid gap-1.5"><span class="text-sm font-semibold text-slate-700">Estimasi subtotal</span><div class="min-h-10 rounded-lg bg-slate-50 px-3 py-2 text-right font-semibold tabular-nums" x-text="`Rp ${lineTotal(item)}`"></div></div>
                        <button type="button" x-on:click="removeItem(index)" x-bind:disabled="items.length === 1" class="min-h-10 rounded-lg px-3 text-sm font-semibold text-danger-600 hover:bg-danger-50 disabled:opacity-40">Hapus</button>
                    </section>
                </template>
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-5">
                <x-ui.button type="button" variant="secondary" x-on:click="addItem()">Tambah Item</x-ui.button>
                <div class="text-right"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimasi total</p><p class="mt-1 text-xl font-bold text-slate-950 tabular-nums" x-text="`Rp ${total()}`"></p><p class="text-xs text-slate-500">Total final dihitung server dengan DECIMAL/BCMath.</p></div>
            </div>
        </x-ui.card>
    </form>
</x-layouts.app>
