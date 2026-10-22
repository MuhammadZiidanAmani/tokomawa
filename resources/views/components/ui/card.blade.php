@props(['title' => null, 'description' => null])

<section {{ $attributes->class(['rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}>
    @if ($title || $description)
        <header class="grid gap-1">
            @if ($title)
                <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="text-sm text-slate-500">{{ $description }}</p>
            @endif
        </header>
    @endif

    <div @class(['mt-5' => $title || $description])>
        {{ $slot }}
    </div>
</section>
