<x-layouts.guest title="Masuk">
    <x-ui.card>
        <div class="grid gap-2">
            <x-ui.badge variant="success" class="w-fit">Akses internal</x-ui.badge>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950">Masuk ke aplikasi</h1>
            <p class="text-sm text-slate-500">Gunakan akun yang dibuat administrator. Registrasi publik tidak tersedia.</p>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success" class="mt-5">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 grid gap-5">
            @csrf

            <x-ui.input
                name="email"
                type="email"
                label="Alamat email"
                placeholder="nama@perusahaan.co.id"
                autocomplete="username"
                inputmode="email"
                required
                autofocus
            />

            <x-ui.input
                name="password"
                type="password"
                label="Password"
                autocomplete="current-password"
                required
            />

            <label class="flex min-h-10 cursor-pointer items-center gap-3 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600">
                Ingat saya di perangkat ini
            </label>

            <x-ui.button type="submit" class="min-h-11 w-full">Masuk</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.guest>
