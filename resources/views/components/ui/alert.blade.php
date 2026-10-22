@props(['variant' => 'info', 'title' => null])

@php
    $variants = [
        'success' => 'border-primary-200 bg-primary-50 text-primary-800',
        'warning' => 'border-amber-200 bg-accent-50 text-accent-700',
        'danger' => 'border-red-200 bg-danger-50 text-danger-600',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    ];
@endphp

<div role="status" {{ $attributes->class(['rounded-lg border p-4', $variants[$variant] ?? $variants['info']]) }}>
    @if ($title)
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['text-sm', 'mt-1' => $title])>{{ $slot }}</div>
</div>
