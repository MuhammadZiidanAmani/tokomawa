@props(['name', 'title', 'description' => null])

<div
    x-data
    x-on:open-modal.window="if ($event.detail === @js($name)) $refs.dialog.showModal()"
    x-on:close-modal.window="if ($event.detail === @js($name)) $refs.dialog.close()"
>
    <dialog
        x-ref="dialog"
        x-on:click="if ($event.target === $refs.dialog) $refs.dialog.close()"
        x-on:keydown.escape.window="$refs.dialog.open && $refs.dialog.close()"
        class="m-auto w-[min(92vw,36rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50"
        aria-labelledby="{{ $name }}-title"
        @if ($description) aria-describedby="{{ $name }}-description" @endif
    >
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="grid gap-1">
                    <h2 id="{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $name }}-description" class="text-sm text-slate-500">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" x-on:click="$refs.dialog.close()" class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Tutup dialog">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="mt-5">{{ $slot }}</div>

            @isset($footer)
                <div class="mt-6 flex justify-end gap-3">{{ $footer }}</div>
            @endisset
        </div>
    </dialog>
</div>
