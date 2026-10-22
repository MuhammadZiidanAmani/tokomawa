@php
    $editing = isset($product);
    $primaryBarcode = $editing ? $product->barcodes->firstWhere('is_primary', true) : null;
    $purchaseUnit = $editing ? $product->units->firstWhere('is_purchase_unit', true) : null;
    $saleUnit = $editing ? $product->units->firstWhere('is_sale_unit', true) : null;
    $alternateBarcodes = $editing ? $product->barcodes->where('is_active', true)->where('is_primary', false)->pluck('barcode')->join(PHP_EOL) : '';
@endphp

<x-layouts.app :title="$editing ? 'Edit Barang' : 'Tambah Barang'">
    <form method="POST" action="{{ $editing ? route('master.products.update', $product) : route('master.products.store') }}" class="grid gap-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-page-header :title="$editing ? 'Edit Barang' : 'Tambah Barang'" description="Isi identitas, satuan, barcode, supplier, dan konfigurasi pajak. Stok tidak dapat diedit dari master.">
            <x-slot:actions><x-ui.button :href="$editing ? route('master.products.show', $product) : route('master.products.index')" variant="secondary">Batal</x-ui.button><x-ui.button type="submit">{{ $editing ? 'Simpan perubahan' : 'Simpan barang' }}</x-ui.button></x-slot:actions>
        </x-page-header>

        @if ($errors->any()) <x-ui.alert variant="danger" title="Periksa kembali input Anda">{{ $errors->first() }}</x-ui.alert> @endif

        <x-ui.card title="Informasi umum" description="SKU bersifat unik dan sebaiknya stabil setelah dipakai transaksi.">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <x-ui.input name="sku" label="SKU" :value="$product->sku ?? ''" placeholder="BRG-000001" required />
                <x-ui.input name="name" label="Nama barang" :value="$product->name ?? ''" required />
                <x-ui.select name="classification" label="Klasifikasi" required>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification->value }}" @selected(old('classification', isset($product) ? $product->classification->value : 'stock') === $classification->value)>{{ $classification->label() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="department_id" label="Departemen"><option value="">Tanpa departemen</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) old('department_id', $product->department_id ?? '') === (string) $department->id)>{{ $department->name }}</option>@endforeach</x-ui.select>
                <x-ui.select name="category_id" label="Kategori"><option value="">Tanpa kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</x-ui.select>
                <x-ui.select name="subcategory_id" label="Subkategori"><option value="">Tanpa subkategori</option>@foreach ($subcategories as $subcategory)<option value="{{ $subcategory->id }}" @selected((string) old('subcategory_id', $product->subcategory_id ?? '') === (string) $subcategory->id)>{{ $subcategory->name }}</option>@endforeach</x-ui.select>
                <x-ui.select name="brand_id" label="Merek"><option value="">Tanpa merek</option>@foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id ?? '') === (string) $brand->id)>{{ $brand->name }}</option>@endforeach</x-ui.select>
                <x-ui.select name="default_supplier_id" label="Supplier utama"><option value="">Belum ditetapkan</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('default_supplier_id', $product->default_supplier_id ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</x-ui.select>
                <x-ui.select name="tax_rule_id" label="Konfigurasi pajak"><option value="">Tidak diklasifikasikan</option>@foreach ($taxRules as $taxRule)<option value="{{ $taxRule->id }}" @selected((string) old('tax_rule_id', $product->tax_rule_id ?? '') === (string) $taxRule->id)>{{ $taxRule->code }} · {{ $taxRule->name }} ({{ $taxRule->rate }}%)</option>@endforeach</x-ui.select>
                <div class="md:col-span-2 xl:col-span-3"><x-ui.textarea name="description" label="Keterangan" :value="$product->description ?? ''" /></div>
            </div>
        </x-ui.card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.card title="Satuan & konversi" description="Faktor menyatakan jumlah satuan dasar untuk satu unit. Gunakan angka desimal, bukan float.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="base_unit_id" label="Satuan dasar" required>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('base_unit_id', $product->base_unit_id ?? '') === (string) $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</x-ui.select>
                    <div></div>
                    <x-ui.select name="purchase_unit_id" label="Satuan pembelian" required>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('purchase_unit_id', $purchaseUnit?->unit_id ?? $product->base_unit_id ?? '') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</x-ui.select>
                    <x-ui.input name="purchase_conversion_factor" label="Faktor pembelian" type="number" min="0.000001" step="0.000001" :value="$purchaseUnit?->conversion_factor ?? '1'" required />
                    <x-ui.select name="sale_unit_id" label="Satuan penjualan" required>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('sale_unit_id', $saleUnit?->unit_id ?? $product->base_unit_id ?? '') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</x-ui.select>
                    <x-ui.input name="sale_conversion_factor" label="Faktor penjualan" type="number" min="0.000001" step="0.000001" :value="$saleUnit?->conversion_factor ?? '1'" required />
                </div>
            </x-ui.card>

            <x-ui.card title="Barcode" description="Barcode utama dan alternatif harus unik secara global.">
                <div class="grid gap-4">
                    <x-ui.input name="primary_barcode" label="Barcode utama" :value="$primaryBarcode?->barcode" />
                    <x-ui.textarea name="alternate_barcodes_text" label="Barcode alternatif" :value="$alternateBarcodes" hint="Satu barcode per baris atau pisahkan dengan koma." />
                </div>
            </x-ui.card>
        </div>

        <x-ui.card title="Kontrol & audit">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.input name="min_stock" label="Stok minimum (referensi)" type="number" min="0" step="0.001" :value="$product->min_stock ?? '0'" required />
                <div class="grid gap-2 text-sm text-slate-700">
                    @foreach ([['track_batch', 'Lacak batch'], ['track_expiry', 'Lacak kedaluwarsa'], ['allow_fractional_qty', 'Izinkan qty pecahan'], ['is_active', 'Barang aktif']] as [$name, $label])
                        <input type="hidden" name="{{ $name }}" value="0">
                        <label class="flex min-h-10 items-center gap-3"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, isset($product) ? $product->{$name} : ($name === 'is_active'))) class="size-4 rounded border-slate-300 text-primary-600">{{ $label }}</label>
                    @endforeach
                </div>
                <x-ui.textarea name="reason" label="Alasan perubahan" required hint="Dicatat pada audit log." />
            </div>
        </x-ui.card>
    </form>
</x-layouts.app>
