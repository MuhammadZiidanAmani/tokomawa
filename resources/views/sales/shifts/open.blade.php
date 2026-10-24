<x-layouts.app title="Buka Shift Kasir">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <p class="text-sm font-semibold text-primary-700">Kasir</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950">Buka Shift Kasir</h1>
            <p class="mt-1 text-sm text-slate-600">Pilih register dan gudang sumber stok sebelum memulai penjualan tunai.</p>
        </div>

        @if ($errors->any())
            <x-ui.alert variant="danger" title="Shift belum dapat dibuka">
                {{ $errors->first() }}
            </x-ui.alert>
        @endif

        <x-ui.card>
            @if ($registers->isEmpty() || $warehouses->isEmpty())
                <div class="py-8 text-center">
                    <p class="font-semibold text-slate-800">Register atau gudang aktif belum tersedia.</p>
                    <p class="mt-1 text-sm text-slate-500">Minta Manager Toko mengaktifkan register dan gudang pada Pengaturan.</p>
                </div>
            @else
                <form method="POST" action="{{ route('cash-shifts.store') }}" class="grid gap-5">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $formKey) }}">

                    <x-ui.select name="register_id" label="Register kasir" required>
                        <option value="">Pilih register</option>
                        @foreach ($registers as $register)
                            <option value="{{ $register->id }}" @selected((string) old('register_id') === (string) $register->id)>{{ $register->code }} · {{ $register->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="warehouse_id" label="Gudang sumber stok" required>
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->code }} · {{ $warehouse->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="opening_float" type="number" label="Modal awal kas" value="0" min="0" step="0.01" inputmode="decimal" required hint="Dicatat sebagai ledger kas pembukaan; penutupan shift berada pada M1.6." />

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-ui.button href="{{ route('dashboard') }}" variant="secondary">Kembali</x-ui.button>
                        <x-ui.button type="submit">Buka Shift &amp; Masuk POS</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
