<div class="grid gap-6">
    <x-page-header
        title="Dashboard"
        description="Fondasi aplikasi sudah aktif. Modul transaksi akan ditambahkan bertahap sesuai milestone yang disetujui."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="$dispatch('open-modal', 'foundation-help')">Tentang fondasi</x-ui.button>
        </x-slot:actions>
    </x-page-header>

    <x-ui.alert variant="warning" title="PostgreSQL development belum terhubung">
        Target database telah ditetapkan ke PostgreSQL, tetapi server lokal dan extension PHP belum tersedia. Test M0.1 berjalan aman pada SQLite in-memory.
    </x-ui.alert>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.card title="Authentication" description="Session-based">
            <x-ui.badge variant="success">Aktif</x-ui.badge>
        </x-ui.card>
        <x-ui.card title="Bahasa & mata uang" description="Indonesia · IDR">
            <x-ui.badge variant="success">Aktif</x-ui.badge>
        </x-ui.card>
        <x-ui.card title="Zona waktu tampilan" description="Asia/Jakarta">
            <x-ui.badge variant="success">Aktif</x-ui.badge>
        </x-ui.card>
        <x-ui.card title="Cabang operasional" description="Disiapkan pada M0.2">
            <x-ui.badge variant="warning">Belum dikonfigurasi</x-ui.badge>
        </x-ui.card>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <x-ui.card title="Status modul" description="Tidak ada data transaksi simulasi pada dashboard fondasi.">
            <x-ui.data-table label="Status kesiapan modul">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Area</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3">Milestone</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">Fondasi teknis & autentikasi</td>
                        <td class="px-4 py-3"><x-ui.badge variant="success">Tersedia</x-ui.badge></td>
                        <td class="px-4 py-3 text-slate-500">M0.1</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">Organisasi & hak akses</td>
                        <td class="px-4 py-3"><x-ui.badge>Belum dimulai</x-ui.badge></td>
                        <td class="px-4 py-3 text-slate-500">M0.2</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">Master & katalog</td>
                        <td class="px-4 py-3"><x-ui.badge>Belum dimulai</x-ui.badge></td>
                        <td class="px-4 py-3 text-slate-500">M0.3</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">Inventory & transaksi</td>
                        <td class="px-4 py-3"><x-ui.badge>Belum dibangun</x-ui.badge></td>
                        <td class="px-4 py-3 text-slate-500">F1+</td>
                    </tr>
                </tbody>
            </x-ui.data-table>
        </x-ui.card>

        <x-ui.card title="Informasi sistem" description="Status lingkungan saat halaman dimuat.">
            <dl class="grid gap-4 text-sm">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-slate-500">Waktu operasional</dt>
                    <dd class="text-right font-semibold text-slate-900">{{ $currentDateTime }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-slate-500">Mode POS MVP</dt>
                    <dd class="font-semibold text-slate-900">Online</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-slate-500">HPP direncanakan</dt>
                    <dd class="text-right font-semibold text-slate-900">Moving Weighted Average</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    <x-ui.modal name="foundation-help" title="Fondasi aplikasi M0.1" description="Komponen dasar yang siap digunakan milestone berikutnya.">
        <ul class="grid list-disc gap-2 pl-5 text-sm text-slate-600">
            <li>Autentikasi session tanpa registrasi publik.</li>
            <li>Layout back-office responsif untuk desktop dan tablet.</li>
            <li>Komponen button, input, card, tabel, modal, badge, dan alert.</li>
            <li>Konfigurasi Bahasa Indonesia, IDR, serta waktu Asia/Jakarta.</li>
        </ul>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$refs.dialog.close()">Tutup</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
