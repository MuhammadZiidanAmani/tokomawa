<x-layouts.app title="Satuan">
    <div class="grid gap-6">
        <x-page-header title="Satuan & Konversi" description="Presisi satuan dikonfigurasi di master; faktor konversi khusus barang dikelola pada form barang.">
            <x-slot:actions><x-ui.button :href="route('master.index')" variant="secondary">Master</x-ui.button></x-slot:actions>
        </x-page-header>
        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert> @endif

        <x-ui.card title="Cari satuan"><form method="GET" class="flex flex-col gap-3 sm:flex-row"><x-ui.input name="search" label="Kode atau nama" :value="request('search')" /><div class="flex items-end gap-2"><x-ui.button type="submit">Cari</x-ui.button><x-ui.button :href="route('master.units.index')" variant="secondary">Reset</x-ui.button></div></form></x-ui.card>

        @can('catalog.manage')
            <x-ui.card title="Tambah satuan" description="Contoh kode: PCS, BOX, PACK, KARTON. Data contoh tidak di-seed otomatis.">
                <form method="POST" action="{{ route('master.units.store') }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">@csrf
                    <x-ui.input name="code" label="Kode" required /><x-ui.input name="name" label="Nama" required /><x-ui.input name="symbol" label="Simbol" required />
                    <x-ui.input name="decimal_places" label="Digit desimal" type="number" min="0" max="6" value="0" required />
                    <div class="grid gap-2"><span class="text-sm font-semibold text-slate-700">Kontrol</span><input type="hidden" name="allow_fractional" value="0"><label class="flex min-h-10 items-center gap-2"><input type="checkbox" name="allow_fractional" value="1" class="size-4 rounded border-slate-300 text-primary-600">Qty pecahan</label><input type="hidden" name="is_active" value="1"></div>
                    <x-ui.input name="reason" label="Alasan" required />
                    <div class="xl:col-span-6"><x-ui.button type="submit">Simpan satuan</x-ui.button></div>
                </form>
            </x-ui.card>
        @endcan

        <x-ui.card title="Daftar satuan">
            @if ($units->isEmpty())
                <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-slate-500">Belum ada satuan.</p>
            @else
                <div class="grid gap-3">
                    @foreach ($units as $unit)
                        <details class="rounded-xl border border-slate-200 p-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><span><strong>{{ $unit->code }} · {{ $unit->name }}</strong><span class="ml-2 text-xs text-slate-500">{{ $unit->symbol }} · {{ $unit->decimal_places }} digit · {{ $unit->base_products_count }} produk dasar</span></span>@can('catalog.manage')<span class="text-sm font-semibold text-primary-700">Edit</span>@endcan</summary>
                            @can('catalog.manage')
                                <form method="POST" action="{{ route('master.units.update', $unit) }}" class="mt-4 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2 xl:grid-cols-5">@csrf @method('PUT')
                                    <x-ui.input name="code" label="Kode" :value="$unit->code" required /><x-ui.input name="name" label="Nama" :value="$unit->name" required /><x-ui.input name="symbol" label="Simbol" :value="$unit->symbol" required /><x-ui.input name="decimal_places" label="Digit desimal" type="number" min="0" max="6" :value="$unit->decimal_places" required />
                                    <div class="grid gap-2"><input type="hidden" name="allow_fractional" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="allow_fractional" value="1" @checked($unit->allow_fractional) class="size-4 rounded">Qty pecahan</label><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($unit->is_active) class="size-4 rounded">Aktif</label></div>
                                    <div class="sm:col-span-2 xl:col-span-5"><x-ui.input name="reason" label="Alasan perubahan" required /></div><div class="xl:col-span-5"><x-ui.button type="submit">Simpan perubahan</x-ui.button></div>
                                </form>
                            @endcan
                        </details>
                    @endforeach
                </div>
                <div class="mt-5">{{ $units->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
