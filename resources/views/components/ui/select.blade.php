@props([
    'name',
    'label' => null,
    'required' => false,
    'errorKey' => null,
])

@php
    $inputId = $attributes->get('id', str_replace(['[', ']'], '', $name));
    $validationKey = $errorKey ?? $name;
    $hasError = $errors->has($validationKey) || $errors->has($validationKey.'.*');
@endphp

<div class="grid gap-1.5">
    @if ($label)
        <label for="{{ $inputId }}" class="text-sm font-semibold text-slate-700">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
                <span class="sr-only">(wajib)</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $inputId }}"
        name="{{ $name }}"
        @required($required)
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->except('id')->class([
            'min-h-10 w-full rounded-lg border bg-white px-3 py-2 text-slate-950 shadow-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500',
            'border-danger-600 focus:border-danger-600' => $hasError,
            'border-slate-300 focus:border-primary-600' => ! $hasError,
        ]) }}
    >
        {{ $slot }}
    </select>

    @if ($hasError)
        <p id="{{ $inputId }}-error" class="text-sm text-danger-600">
            {{ $errors->first($validationKey) ?: $errors->first($validationKey.'.*') }}
        </p>
    @endif
</div>
