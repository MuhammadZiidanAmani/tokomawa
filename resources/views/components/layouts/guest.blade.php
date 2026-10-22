@props(['title' => null])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.72fr)]">
            <section class="hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <x-brand-logo class="[&_span]:text-white" />
                <div class="max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-emerald-300">Retail management system</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight">Operasional retail yang rapi, cepat, dan dapat diaudit.</h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">Fondasi aman untuk purchasing, inventory, POS, promosi, dan laporan dalam satu aplikasi modular.</p>
                </div>
                <p class="text-sm text-slate-500">Bahasa Indonesia · IDR · Asia/Jakarta</p>
            </section>

            <section class="flex items-center justify-center bg-slate-50 px-4 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <div class="mb-8 lg:hidden">
                        <x-brand-logo />
                    </div>
                    {{ $slot }}
                </div>
            </section>
        </main>
        @livewireScripts
    </body>
</html>
