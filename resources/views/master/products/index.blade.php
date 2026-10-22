<x-layouts.app title="Barang">
    <div class="grid gap-6">
        <x-page-header title="Master Barang" description="Cari berdasarkan SKU, barcode, atau nama. Tidak ada aksi edit stok pada halaman ini.">
            <x-slot:actions>
                <x-ui.button :href="route('master.index')" variant="secondary">Master</x-ui.button>
                @can('create', App\Domain\Catalog\Models\Product::class)
                    <x-ui.button :href="route('master.products.create')">Tambah barang</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif

        <x-ui.card title="Pencarian & filter" description="Hasil selalu dipaginasi 20 barang per halaman.">
            <form method="GET" action="{{ route('master.products.index') }}" class="grid gap-4 md:grid-cols-4">
                <x-ui.input name="search" label="SKU, barcode, atau nama" :value="request('search')" placeholder="Cari barang..." />
                <x-ui.select name="category_id" label="Kategori">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="status" label="Status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </x-ui.select>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit">Terapkan</x-ui.button>
                    <x-ui.button :href="route('master.products.index')" variant="secondary">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($products->isEmpty())
            <x-ui.card><p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Belum ada barang yang cocok dengan filter.</p></x-ui.card>
        @else
            <x-ui.data-table label="Daftar barang">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">SKU</th><th class="px-4 py-3">Barang</th><th class="px-4 py-3">Barcode utama</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Satuan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($products as $product)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold">{{ $product->sku }}</td>
                            <td class="px-4 py-3"><p class="font-semibold text-slate-950">{{ $product->name }}</p><p class="text-xs text-slate-500">{{ $product->brand?->name ?? 'Tanpa merek' }}</p></td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $product->barcodes->firstWhere('is_primary', true)?->barcode ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $product->baseUnit->symbol }}</td>
                            <td class="px-4 py-3"><x-ui.badge :variant="$product->is_active ? 'success' : 'neutral'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('master.products.show', $product) }}" class="font-semibold text-primary-700 hover:underline">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.data-table>
            <div>{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.app>
