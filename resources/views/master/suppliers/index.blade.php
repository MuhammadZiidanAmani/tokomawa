<x-layouts.app title="Supplier">
    <div class="grid gap-6">
        <x-page-header title="Master Supplier" description="Kelola identitas, kontak, administrasi, dan relasi barang supplier.">
            <x-slot:actions><x-ui.button :href="route('master.index')" variant="secondary">Master</x-ui.button>@can('create', App\Domain\Suppliers\Models\Supplier::class)<x-ui.button :href="route('master.suppliers.create')">Tambah supplier</x-ui.button>@endcan</x-slot:actions>
        </x-page-header>
        @if (session('status')) <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert> @endif
        <x-ui.card title="Pencarian & filter"><form method="GET" class="grid gap-4 md:grid-cols-3"><x-ui.input name="search" label="Kode, nama, atau telepon" :value="request('search')" /><x-ui.select name="status" label="Status"><option value="">Semua</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option></x-ui.select><div class="flex items-end gap-2"><x-ui.button type="submit">Terapkan</x-ui.button><x-ui.button :href="route('master.suppliers.index')" variant="secondary">Reset</x-ui.button></div></form></x-ui.card>
        @if ($suppliers->isEmpty())
            <x-ui.card><p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">Belum ada supplier yang cocok.</p></x-ui.card>
        @else
            <x-ui.data-table label="Daftar supplier"><thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3">Kontak</th><th class="px-4 py-3 text-right">Barang</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($suppliers as $supplier)<tr><td class="px-4 py-3 font-mono text-xs font-semibold">{{ $supplier->code }}</td><td class="px-4 py-3"><p class="font-semibold">{{ $supplier->name }}</p><p class="text-xs text-slate-500">{{ $supplier->contact_name ?? 'PIC belum diisi' }}</p></td><td class="px-4 py-3 text-slate-600">{{ $supplier->phone ?? $supplier->email ?? '—' }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $supplier->supplier_products_count }}</td><td class="px-4 py-3"><x-ui.badge :variant="$supplier->is_active ? 'success' : 'neutral'">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge></td><td class="px-4 py-3 text-right"><a href="{{ route('master.suppliers.show', $supplier) }}" class="font-semibold text-primary-700 hover:underline">Detail</a></td></tr>@endforeach</tbody></x-ui.data-table>
            <div>{{ $suppliers->links() }}</div>
        @endif
    </div>
</x-layouts.app>
