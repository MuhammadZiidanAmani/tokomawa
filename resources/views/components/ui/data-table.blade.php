@props(['label' => 'Tabel data'])

<div {{ $attributes->class(['overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200" aria-label="{{ $label }}">
            {{ $slot }}
        </table>
    </div>
</div>
