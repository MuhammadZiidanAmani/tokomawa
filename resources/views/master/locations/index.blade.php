<x-layouts.app title="Rak & Lokasi">
    <div class="grid gap-6">
        <x-page-header title="Rak & Lokasi Barang" :description="$branch ? 'Cabang aktif: '.$branch->name : 'Cabang aktif belum dikonfigurasi.'"><x-slot:actions><x-ui.button :href="route('master.index')" variant="secondary">Master</x-ui.button></x-slot:actions></x-page-header>
        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert> @endif
        @if (! $branch)
            <x-ui.alert variant="warning" title="Cabang belum tersedia">Buat dan pilih cabang aktif dari Pengaturan sebelum mengelola rak.</x-ui.alert>
        @else
            <x-ui.card title="Cari lokasi"><form method="GET" class="flex flex-col gap-3 sm:flex-row"><x-ui.input name="search" label="Kode atau nama rak" :value="request('search')" /><div class="flex items-end gap-2"><x-ui.button type="submit">Cari</x-ui.button><x-ui.button :href="route('master.locations.index')" variant="secondary">Reset</x-ui.button></div></form></x-ui.card>
            @can('locations.manage')
                <x-ui.card title="Tambah rak" description="Kode unik di dalam gudang yang dipilih."><form method="POST" action="{{ route('master.locations.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf
                    <x-ui.select name="warehouse_id" label="Gudang" required><option value="">Pilih gudang</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</x-ui.select><x-ui.input name="code" label="Kode rak" required /><x-ui.input name="name" label="Nama lokasi" required /><x-ui.input name="zone" label="Zona" /><x-ui.input name="aisle" label="Lorong" /><x-ui.input name="level" label="Tingkat" /><input type="hidden" name="is_active" value="1"><div class="md:col-span-2"><x-ui.input name="reason" label="Alasan" required /></div><div class="xl:col-span-4"><x-ui.button type="submit">Simpan rak</x-ui.button></div>
                </form></x-ui.card>
            @endcan
            <x-ui.card title="Daftar rak" description="Penempatan barang tidak memindahkan stok; movement lokasi baru tersedia pada modul inventory.">
                @if ($locations->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Belum ada rak pada cabang aktif.</p>
                @else
                    <div class="grid gap-4">
                        @foreach ($locations as $location)
                            <details class="rounded-xl border border-slate-200 p-4">
                                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3"><span><strong>{{ $location->code }} · {{ $location->name }}</strong><span class="ml-2 text-xs text-slate-500">{{ $location->warehouse->name }} · {{ $location->productLocations->count() }} barang</span></span><x-ui.badge :variant="$location->is_active ? 'success' : 'neutral'">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge></summary>
                                <div class="mt-4 grid gap-5 border-t border-slate-100 pt-4 xl:grid-cols-2">
                                    <div><p class="text-sm font-semibold">Barang ditempatkan</p><div class="mt-2 flex flex-wrap gap-2">@forelse ($location->productLocations as $assignment)<x-ui.badge :variant="$assignment->is_primary ? 'success' : 'neutral'">{{ $assignment->product->sku }} · {{ $assignment->product->name }}</x-ui.badge>@empty<span class="text-sm text-slate-500">Belum ada barang.</span>@endforelse</div></div>
                                    @can('locations.manage')
                                        <form method="POST" action="{{ route('master.locations.products.store', $location) }}" class="grid gap-3 sm:grid-cols-2">@csrf<x-ui.select name="product_id" label="Tempatkan barang" required><option value="">Pilih barang</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} · {{ $product->name }}</option>@endforeach</x-ui.select><div class="grid gap-2"><input type="hidden" name="is_primary" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1" class="size-4 rounded">Lokasi utama</label><input type="hidden" name="is_active" value="1"></div><div class="sm:col-span-2"><x-ui.input name="reason" label="Alasan" required /></div><div class="sm:col-span-2"><x-ui.button type="submit">Simpan penempatan</x-ui.button></div></form>
                                        <form method="POST" action="{{ route('master.locations.update', $location) }}" class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 xl:col-span-2">@csrf @method('PUT')<x-ui.select name="warehouse_id" label="Gudang" required>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($location->warehouse_id === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</x-ui.select><x-ui.input name="code" label="Kode" :value="$location->code" required /><x-ui.input name="name" label="Nama" :value="$location->name" required /><x-ui.input name="zone" label="Zona" :value="$location->zone" /><x-ui.input name="aisle" label="Lorong" :value="$location->aisle" /><x-ui.input name="level" label="Tingkat" :value="$location->level" /><div><input type="hidden" name="is_active" value="0"><label class="flex min-h-10 items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="size-4 rounded">Aktif</label></div><x-ui.input name="reason" label="Alasan perubahan" required /><div class="sm:col-span-2"><x-ui.button type="submit">Simpan perubahan rak</x-ui.button></div></form>
                                    @endcan
                                </div>
                            </details>
                        @endforeach
                    </div>
                    <div class="mt-5">{{ $locations->links() }}</div>
                @endif
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
