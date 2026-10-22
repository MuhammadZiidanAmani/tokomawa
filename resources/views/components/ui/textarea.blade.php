@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
])

@php
    $inputId = $attributes->get('id', $name);
    $hasError = $errors->has($name);
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

    <textarea
        id="{{ $inputId }}"
        name="{{ $name }}"
        @required($required)
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @elseif ($hint) aria-describedby="{{ $inputId }}-hint" @endif
        {{ $attributes->except('id')->class([
            'min-h-24 w-full rounded-lg border bg-white px-3 py-2 text-slate-950 shadow-sm placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500',
            'border-danger-600 focus:border-danger-600' => $hasError,
            'border-slate-300 focus:border-primary-600' => ! $hasError,
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p id="{{ $inputId }}-error" class="text-sm text-danger-600">{{ $message }}</p>
    @elseif ($hint)
        <p id="{{ $inputId }}-hint" class="text-xs text-slate-500">{{ $hint }}</p>
    @enderror
</div>
