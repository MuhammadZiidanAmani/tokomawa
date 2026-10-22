<x-layouts.app title="Referensi Katalog">
    <div class="grid gap-6">
        <x-page-header title="Departemen, Kategori & Merek" description="Hierarki katalog memakai inaktivasi; data yang sudah direferensikan tidak dihapus permanen.">
            <x-slot:actions><x-ui.button :href="route('master.index')" variant="secondary">Master</x-ui.button></x-slot:actions>
        </x-page-header>

        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert variant="danger" title="Periksa kembali input Anda">{{ $errors->first() }}</x-ui.alert> @endif

        <x-ui.card title="Cari referensi">
            <form method="GET" action="{{ route('master.references.index') }}" class="flex flex-col gap-3 sm:flex-row">
                <x-ui.input name="search" label="Kode atau nama departemen" :value="request('search')" class="sm:min-w-80" />
                <div class="flex items-end gap-2"><x-ui.button type="submit">Cari</x-ui.button><x-ui.button :href="route('master.references.index')" variant="secondary">Reset</x-ui.button></div>
            </form>
        </x-ui.card>

        @can('catalog.manage')
            <section class="grid gap-4 xl:grid-cols-2">
                <x-ui.card title="Tambah departemen">
                    <form method="POST" action="{{ route('master.departments.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <x-ui.input name="code" label="Kode" required /><x-ui.input name="name" label="Nama" required />
                        <div class="sm:col-span-2"><x-ui.textarea name="description" label="Keterangan" /></div>
                        <input type="hidden" name="is_active" value="1"><div class="sm:col-span-2"><x-ui.input name="reason" label="Alasan" required /></div>
                        <div class="sm:col-span-2"><x-ui.button type="submit">Simpan departemen</x-ui.button></div>
                    </form>
                </x-ui.card>
                <x-ui.card title="Tambah kategori">
                    <form method="POST" action="{{ route('master.categories.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <x-ui.select name="department_id" label="Departemen" required><option value="">Pilih departemen</option>@foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</x-ui.select>
                        <x-ui.input name="code" label="Kode" required /><x-ui.input name="name" label="Nama" required />
                        <input type="hidden" name="is_active" value="1"><x-ui.input name="reason" label="Alasan" required />
                        <div class="sm:col-span-2"><x-ui.button type="submit">Simpan kategori</x-ui.button></div>
                    </form>
                </x-ui.card>
                <x-ui.card title="Tambah subkategori">
                    <form method="POST" action="{{ route('master.subcategories.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <x-ui.select name="category_id" label="Kategori" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->department->name }} / {{ $category->name }}</option>@endforeach</x-ui.select>
                        <x-ui.input name="code" label="Kode" required /><x-ui.input name="name" label="Nama" required />
                        <input type="hidden" name="is_active" value="1"><x-ui.input name="reason" label="Alasan" required />
                        <div class="sm:col-span-2"><x-ui.button type="submit">Simpan subkategori</x-ui.button></div>
                    </form>
                </x-ui.card>
                <x-ui.card title="Tambah merek">
                    <form method="POST" action="{{ route('master.brands.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <x-ui.input name="code" label="Kode" required /><x-ui.input name="name" label="Nama" required />
                        <input type="hidden" name="is_active" value="1"><x-ui.input name="reason" label="Alasan" required />
                        <div class="sm:col-span-2"><x-ui.button type="submit">Simpan merek</x-ui.button></div>
                    </form>
                </x-ui.card>
            </section>
        @endcan

        @foreach ([
            ['Departemen', $departments, 'department', 'master.departments.update'],
            ['Kategori', $categories, 'category', 'master.categories.update'],
            ['Subkategori', $subcategories, 'subcategory', 'master.subcategories.update'],
            ['Merek', $brands, 'brand', 'master.brands.update'],
        ] as [$heading, $records, $type, $routeName])
            <x-ui.card :title="$heading" description="Klik Edit untuk mengubah identitas atau menonaktifkan data.">
                @if ($records->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-slate-500">Belum ada data {{ strtolower($heading) }}.</p>
                @else
                    <div class="grid gap-3">
                        @foreach ($records as $record)
                            <details class="rounded-xl border border-slate-200 p-4">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                                    <span><strong>{{ $record->code }} · {{ $record->name }}</strong><span class="ml-2 text-xs text-slate-500">{{ $record->is_active ? 'Aktif' : 'Nonaktif' }} · {{ $record->products_count ?? 0 }} barang</span></span>
                                    @can('catalog.manage')<span class="text-sm font-semibold text-primary-700">Edit</span>@endcan
                                </summary>
                                @can('catalog.manage')
                                    <form method="POST" action="{{ route($routeName, $record) }}" class="mt-4 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">@csrf @method('PUT')
                                        @if ($type === 'category')
                                            <x-ui.select name="department_id" label="Departemen" required>@foreach ($departments as $option)<option value="{{ $option->id }}" @selected($record->department_id === $option->id)>{{ $option->name }}</option>@endforeach</x-ui.select>
                                        @elseif ($type === 'subcategory')
                                            <x-ui.select name="category_id" label="Kategori" required>@foreach ($categories as $option)<option value="{{ $option->id }}" @selected($record->category_id === $option->id)>{{ $option->name }}</option>@endforeach</x-ui.select>
                                        @endif
                                        <x-ui.input name="code" label="Kode" :value="$record->code" required /><x-ui.input name="name" label="Nama" :value="$record->name" required />
                                        @if (in_array($type, ['department', 'category', 'subcategory'], true))<x-ui.textarea name="description" label="Keterangan" :value="$record->description" />@endif
                                        <div class="grid gap-2"><input type="hidden" name="is_active" value="0"><label class="flex min-h-10 items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($record->is_active) class="size-4 rounded border-slate-300 text-primary-600">Aktif</label></div>
                                        <x-ui.input name="reason" label="Alasan perubahan" required />
                                        <div class="sm:col-span-2"><x-ui.button type="submit">Simpan perubahan</x-ui.button></div>
                                    </form>
                                @endcan
                            </details>
                        @endforeach
                    </div>
                    <div class="mt-5">{{ $records->links() }}</div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
