@props(['variant' => 'neutral'])

@php
    $variants = [
        'success' => 'bg-primary-50 text-primary-800 ring-primary-600/20',
        'warning' => 'bg-accent-50 text-accent-700 ring-accent-500/30',
        'danger' => 'bg-danger-50 text-danger-600 ring-danger-600/20',
        'info' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $variants[$variant] ?? $variants['neutral']]) }}>
    {{ $slot }}
</span>
