<x-layouts.app title="Persediaan">
    <div class="grid gap-6">
        <x-page-header title="Dashboard Persediaan" description="Ringkasan saldo aktual dari proyeksi ledger pada cabang aktif.">
            <x-slot:actions>
                @can('inventory.opening.create')
                    <x-ui.button :href="route('inventory.opening.index')">Input saldo awal</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-ui.alert variant="success" title="Berhasil">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card title="Barang bersaldo"><p class="text-3xl font-bold tabular-nums text-slate-950">{{ number_format($totalProducts, 0, ',', '.') }}</p></x-ui.card>
            <x-ui.card title="Total kuantitas"><p class="text-3xl font-bold tabular-nums text-slate-950">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->quantity((string) $totalQuantity) }}</p></x-ui.card>
            <x-ui.card title="Nilai persediaan"><p class="text-2xl font-bold tabular-nums text-slate-950">{{ app(\App\Domain\Shared\Support\IndonesiaFormatter::class)->currencyDecimal((string) $totalValue) }}</p></x-ui.card>
            <x-ui.card title="Menunggu persetujuan"><p class="text-3xl font-bold tabular-nums text-amber-700">{{ $pendingCount }}</p></x-ui.card>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('inventory.balances') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary-500"><h2 class="font-semibold text-slate-950">Saldo stok</h2><p class="mt-2 text-sm text-slate-500">Stok per barang, gudang, batch, dan status.</p></a>
            <a href="{{ route('inventory.movements') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary-500"><h2 class="font-semibold text-slate-950">Riwayat mutasi</h2><p class="mt-2 text-sm text-slate-500">Ledger immutable seluruh perubahan stok.</p></a>
            <a href="{{ route('inventory.opening.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary-500"><h2 class="font-semibold text-slate-950">Saldo awal</h2><p class="mt-2 text-sm text-slate-500">Pengajuan dan persetujuan stok awal.</p></a>
            @can('inventory.reconcile')
                <a href="{{ route('inventory.reconciliation') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary-500"><h2 class="font-semibold text-slate-950">Rekonsiliasi</h2><p class="mt-2 text-sm text-slate-500">Bandingkan ledger dengan proyeksi saldo.</p></a>
            @endcan
        </div>

        <x-ui.card title="Mutasi terbaru" description="Data transaksi aktual; tidak ada KPI atau transaksi contoh.">
            @if ($recentMovements->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">Belum ada mutasi stok pada cabang ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500"><tr><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Gudang</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2">Sumber</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentMovements as $movement)
                                <tr><td class="px-3 py-3">{{ $movement->posted_at->timezone(config('retail.display_timezone'))->format('d/m/Y H:i') }}</td><td class="px-3 py-3 font-medium">{{ $movement->product->sku }} · {{ $movement->product->name }}</td><td class="px-3 py-3">{{ $movement->warehouse->name }}</td><td @class(['px-3 py-3 text-right font-semibold tabular-nums', 'text-primary-700' => $movement->direction === 'in', 'text-danger-600' => $movement->direction === 'out'])>{{ $movement->qty_delta }}</td><td class="px-3 py-3 font-mono text-xs">{{ $movement->source_id }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
