@props(['compact' => false])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-600 text-white shadow-sm" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 8.25 12 3l8.25 5.25L12 13.5 3.75 8.25Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 12.75 8.25 5.25 8.25-5.25M3.75 17.25 12 22.5l8.25-5.25" />
        </svg>
    </span>

    @unless ($compact)
        <span class="min-w-0">
            <span class="block truncate text-base font-bold tracking-tight text-slate-950">Tokomawa Retail</span>
            <span class="block text-xs text-slate-500">Sistem Manajemen Retail</span>
        </span>
    @endunless
</div>
