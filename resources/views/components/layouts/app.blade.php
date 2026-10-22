@props(['title' => null])

<!DOCTYPE html>
<html lang="id" x-data>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 font-semibold text-primary-700 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten utama</a>

        <div class="min-h-screen lg:pl-[248px]">
            <aside class="fixed inset-y-0 left-0 z-40 hidden w-[248px] lg:block">
                <x-navigation.sidebar />
            </aside>

            <dialog
                x-ref="mobileNavigation"
                x-on:click="if ($event.target === $refs.mobileNavigation) $refs.mobileNavigation.close()"
                class="fixed inset-0 z-50 m-0 h-dvh max-h-none w-full max-w-none bg-slate-950/60 p-0 lg:hidden"
                aria-label="Navigasi"
            >
                <aside class="h-full w-[min(84vw,20rem)] shadow-2xl">
                    <x-navigation.sidebar mobile />
                </aside>
            </dialog>

            <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" x-on:click="$refs.mobileNavigation.showModal()" class="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka navigasi">
                        <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <p class="truncate text-xs text-slate-500">Cabang</p>
                        @if (isset($accessibleBranches) && $accessibleBranches->count() > 1)
                            <form method="POST" action="{{ route('current-branch.store') }}">
                                @csrf
                                <label for="topbar-current-branch" class="sr-only">Cabang aktif</label>
                                <select
                                    id="topbar-current-branch"
                                    name="branch"
                                    x-on:change="$el.form.submit()"
                                    class="max-w-56 rounded-md border-0 bg-transparent p-0 text-sm font-semibold text-slate-800 focus:ring-0"
                                >
                                    @foreach ($accessibleBranches as $branchOption)
                                        <option value="{{ $branchOption->public_id }}" @selected($currentBranch?->is($branchOption))>
                                            {{ $branchOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $currentBranch?->name ?? 'Belum dikonfigurasi' }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="max-w-48 truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                        <p class="max-w-48 truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">Keluar</x-ui.button>
                    </form>
                </div>
            </header>

            <main id="main-content" class="mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
