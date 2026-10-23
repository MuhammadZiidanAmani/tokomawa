@props(['mobile' => false])

<div class="flex h-full flex-col bg-slate-950 text-slate-200">
    <div class="flex h-16 items-center justify-between gap-3 border-b border-white/10 px-5">
        <x-brand-logo class="[&_span]:text-white" />
        @if ($mobile)
            <button
                type="button"
                x-on:click="$refs.mobileNavigation.close()"
                class="grid size-10 shrink-0 place-items-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white"
                aria-label="Tutup navigasi"
            >
                <span class="text-2xl leading-none" aria-hidden="true">&times;</span>
            </button>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" @class([
            'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
            'bg-primary-600 text-white' => request()->routeIs('dashboard'),
            'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('dashboard'),
        ])>
            <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5.25 9.75V21h13.5V9.75M9 21v-6h6v6" />
            </svg>
            Dashboard
        </a>

        @can('master.view')
            <a href="{{ route('master.index') }}" @class([
                'mt-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                'bg-primary-600 text-white' => request()->routeIs('master.*'),
                'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('master.*'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5h16v13H4zM8 9h8M8 13h5" />
                </svg>
                Master
            </a>
        @endcan

        @can('purchase_orders.view')
            <a href="{{ route('purchasing.purchase-orders.index') }}" @class([
                'mt-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                'bg-primary-600 text-white' => request()->routeIs('purchasing.purchase-orders.*'),
                'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('purchasing.purchase-orders.*'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l3 3v15H6V3Zm3 7h6M9 14h6M9 18h4" />
                </svg>
                Purchase Order
            </a>
        @endcan

        @can('goods_receipts.view')
            <a href="{{ route('purchasing.goods-receipts.index') }}" @class([
                'mt-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                'bg-primary-600 text-white' => request()->routeIs('purchasing.goods-receipts.*'),
                'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('purchasing.goods-receipts.*'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h12v12H3v-12Zm12 4h3l3 3v5.5h-6V11.5ZM6.5 11h5M6.5 14h3M6.5 20.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                </svg>
                Penerimaan Barang
            </a>
        @endcan

        @can('inventory.view')
            <a href="{{ route('inventory.index') }}" @class([
                'mt-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                'bg-primary-600 text-white' => request()->routeIs('inventory.*'),
                'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('inventory.*'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8-4 8 4-8 4-8-4Zm0 5 8 4 8-4M4 17l8 4 8-4" />
                </svg>
                Persediaan
            </a>
        @endcan

        @can('settings.view')
            <a href="{{ route('settings.governance') }}" @class([
                'mt-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                'bg-primary-600 text-white' => request()->routeIs('settings.*'),
                'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('settings.*'),
            ])>
                <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.7 7.7 0 0 0-.09-1.17l2.02-1.57-2-3.46-2.47 1a7.65 7.65 0 0 0-2.03-1.18L14.57 3h-4l-.36 2.62A7.65 7.65 0 0 0 8.18 6.8l-2.47-1-2 3.46 2.02 1.57A7.7 7.7 0 0 0 5.64 12c0 .4.03.79.09 1.17l-2.02 1.57 2 3.46 2.47-1c.62.5 1.3.9 2.03 1.18l.36 2.62h4l.36-2.62a7.65 7.65 0 0 0 2.03-1.18l2.47 1 2-3.46-2.02-1.57c.06-.38.09-.77.09-1.17Z" />
                </svg>
                Pengaturan
            </a>
        @endcan

        <div class="mt-7 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Modul berikutnya</div>
        <ul class="mt-2 grid gap-1" aria-label="Modul yang belum tersedia">
            @foreach (['Penjualan', 'Promosi', 'Laporan'] as $module)
                <li class="flex min-h-10 items-center justify-between gap-2 rounded-lg px-3 py-2 text-slate-500" title="Belum tersedia pada M0.1">
                    <span>{{ $module }}</span>
                    <span class="text-[10px] uppercase tracking-wide">Nanti</span>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-500">
        Fondasi sampai Penerimaan Barang M1.3
    </div>
</div>
