@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition duration-150 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
    $variants = [
        'primary' => 'bg-primary-600 text-white shadow-sm hover:bg-primary-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'bg-danger-600 text-white shadow-sm hover:bg-red-800',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </button>
@endif
