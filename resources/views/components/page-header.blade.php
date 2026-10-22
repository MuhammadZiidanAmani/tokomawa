@props(['title', 'description' => null])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="grid gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-slate-950">{{ $title }}</h1>
        @if ($description)
            <p class="max-w-3xl text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
