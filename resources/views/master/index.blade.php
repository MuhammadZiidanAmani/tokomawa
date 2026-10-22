<x-layouts.app title="Master Data">
    <div class="grid gap-6">
        <x-page-header title="Master Data Retail" description="Kelola katalog, supplier, lokasi, harga, dan pajak tanpa mengubah stok on-hand.">
            <x-slot:actions>
                @can('catalog.manage')
                    <x-ui.button :href="route('master.products.create')">Tambah barang</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-ui.alert variant="info" title="Batas milestone">
            Master data tidak menyediakan input stok. Saldo stok hanya akan berubah melalui ledger pada milestone inventory.
        </x-ui.alert>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @php
                $modules = [
                    ['Barang', 'SKU, barcode, satuan, klasifikasi, pajak, dan supplier.', 'master.products.index', 'catalog.view'],
                    ['Departemen & Kategori', 'Hierarki departemen, kategori, subkategori, dan merek.', 'master.references.index', 'catalog.view'],
                    ['Satuan & Konversi', 'Presisi satuan dan konversi per barang.', 'master.units.index', 'catalog.view'],
                    ['Supplier', 'Identitas supplier dan relasi barang per supplier.', 'master.suppliers.index', 'suppliers.view'],
                    ['Rak & Lokasi', 'Rak per cabang/gudang serta penempatan barang.', 'master.locations.index', 'locations.view'],
                    ['Strata Harga & Pajak', 'Harga efektif, riwayat perubahan, dan konfigurasi pajak.', 'master.pricing.index', 'pricing.view'],
                ];
            @endphp

            @foreach ($modules as [$title, $description, $routeName, $permission])
                @can($permission)
                    <a href="{{ route($routeName) }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-500 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none">
                        <h2 class="text-base font-semibold text-slate-950 group-hover:text-primary-700">{{ $title }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
                        <span class="mt-5 inline-flex text-sm font-semibold text-primary-700">Buka modul →</span>
                    </a>
                @endcan
            @endforeach
        </div>
    </div>
</x-layouts.app>
