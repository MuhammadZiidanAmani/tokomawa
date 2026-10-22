<x-layouts.app title="Pengaturan">
    <div class="grid gap-6">
        <x-page-header
            title="Organisasi & Tata Kelola"
            description="Kelola cabang, gudang, register, hak akses, batas persetujuan, dan jejak audit dari data aplikasi."
        />

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" title="Periksa kembali input Anda">
                {{ $errors->first() }}
            </x-ui.alert>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card title="Cabang dapat diakses" description="Scope pengguna saat ini">
                <p class="text-3xl font-bold tabular-nums text-slate-950">{{ $branches->count() }}</p>
            </x-ui.card>
            <x-ui.card title="Cabang aktif" description="Konteks transaksi dan audit">
                <p class="text-base font-semibold text-slate-950">{{ $currentBranch?->name ?? 'Belum tersedia' }}</p>
            </x-ui.card>
            <x-ui.card title="Role aktif" description="Matriks akses sistem">
                <p class="text-3xl font-bold tabular-nums text-slate-950">{{ $roles->count() }}</p>
            </x-ui.card>
            <x-ui.card title="Status governance" description="Audit, approval, idempotency, sequence">
                <x-ui.badge variant="success">Aktif</x-ui.badge>
            </x-ui.card>
        </div>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
            <x-ui.card title="Cabang, gudang, dan register" description="Data organisasi yang menjadi scope seluruh transaksi berikutnya.">
                @if ($branches->isEmpty())
                    <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                        Belum ada cabang. Super Admin dapat membuat cabang operasional pertama melalui formulir di samping.
                    </div>
                @else
                    <div class="grid gap-4">
                        @foreach ($branches as $branch)
                            <article class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-950">{{ $branch->name }}</h3>
                                        <p class="text-xs text-slate-500">{{ $branch->code }} · {{ $branch->timezone }}</p>
                                    </div>
                                    <x-ui.badge variant="{{ $currentBranch?->is($branch) ? 'success' : 'neutral' }}">
                                        {{ $currentBranch?->is($branch) ? 'Cabang aktif' : 'Tersedia' }}
                                    </x-ui.badge>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gudang</p>
                                        <p class="mt-1 text-sm text-slate-700">
                                            {{ $branch->warehouses->isEmpty() ? 'Belum dikonfigurasi' : $branch->warehouses->pluck('name')->join(', ') }}
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Register</p>
                                        <p class="mt-1 text-sm text-slate-700">
                                            {{ $branch->registers->isEmpty() ? 'Belum dikonfigurasi' : $branch->registers->pluck('name')->join(', ') }}
                                        </p>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @can('branches.manage')
                <x-ui.card title="Tambah cabang" description="Kode cabang unik dan zona waktu wajib ditetapkan.">
                    <form method="POST" action="{{ route('settings.branches.store') }}" class="grid gap-4">
                        @csrf
                        <x-ui.input name="code" label="Kode cabang" placeholder="JKT-01" required />
                        <x-ui.input name="name" label="Nama cabang" placeholder="Cabang Utama" required />
                        <x-ui.input name="timezone" label="Zona waktu" value="Asia/Jakarta" required />
                        <x-ui.button type="submit" class="w-full">Tambah cabang</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </section>

        @if ($currentBranch)
            <section class="grid gap-6 lg:grid-cols-2">
                @can('warehouses.manage')
                    <x-ui.card title="Tambah gudang" description="Cabang aktif: {{ $currentBranch->name }}">
                        <form method="POST" action="{{ route('settings.warehouses.store', $currentBranch) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            <x-ui.input name="code" label="Kode gudang" placeholder="GDG-01" required />
                            <x-ui.input name="name" label="Nama gudang" placeholder="Gudang Utama" required />
                            <div class="sm:col-span-2"><x-ui.button type="submit">Tambah gudang</x-ui.button></div>
                        </form>
                    </x-ui.card>
                @endcan

                @can('registers.manage')
                    <x-ui.card title="Tambah register" description="Cabang aktif: {{ $currentBranch->name }}">
                        <form method="POST" action="{{ route('settings.registers.store', $currentBranch) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            <x-ui.input name="code" label="Kode register" placeholder="REG-01" required />
                            <x-ui.input name="name" label="Nama register" placeholder="Kasir 01" required />
                            <div class="sm:col-span-2"><x-ui.button type="submit">Tambah register</x-ui.button></div>
                        </form>
                    </x-ui.card>
                @endcan
            </section>
        @endif

        @can('access.view')
            <x-ui.card title="Matriks hak akses" description="Izin server-side per role. Super Admin dan Owner memiliki seluruh izin governance.">
                <x-ui.data-table label="Matriks hak akses">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3" scope="col">Role</th>
                            <th class="px-4 py-3" scope="col">Izin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($roles as $role)
                            <tr>
                                <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $role->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $role->permissions->pluck('name')->join(', ') ?: 'Tidak ada izin governance langsung' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.data-table>
            </x-ui.card>
        @endcan

        @can('access.manage')
            <x-ui.card title="Tetapkan role & cabang pengguna" description="Pilihan pertama pada daftar cabang menjadi cabang default pengguna.">
                <form method="POST" action="{{ route('settings.access.store') }}" class="grid gap-4 lg:grid-cols-2">
                    @csrf
                    <x-ui.select name="user_id" label="Pengguna" required>
                        <option value="">Pilih pengguna</option>
                        @foreach ($users as $managedUser)
                            <option value="{{ $managedUser->id }}" @selected((string) old('user_id') === (string) $managedUser->id)>
                                {{ $managedUser->name }} · {{ $managedUser->email }}
                            </option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select name="role_ids[]" error-key="role_ids" label="Role" multiple required class="min-h-32">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(in_array($role->id, old('role_ids', [])))>{{ $role->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select name="branch_ids[]" error-key="branch_ids" label="Cabang" multiple class="min-h-32">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(in_array($branch->id, old('branch_ids', [])))>{{ $branch->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="reason" label="Alasan perubahan" placeholder="Contoh: penempatan kasir baru" required />
                    <div class="lg:col-span-2"><x-ui.button type="submit">Simpan hak akses</x-ui.button></div>
                </form>
                <div class="mt-6">{{ $users->links() }}</div>
            </x-ui.card>
        @endcan

        @if ($currentBranch)
            @can('approval_limits.manage')
                <section class="grid gap-6 xl:grid-cols-[minmax(20rem,0.65fr)_minmax(0,1.35fr)]">
                    <x-ui.card title="Konfigurasi batas persetujuan" description="Nilai tidak diasumsikan; tentukan sesuai kebijakan toko.">
                        <form method="POST" action="{{ route('settings.approval-limits.store', $currentBranch) }}" class="grid gap-4">
                            @csrf
                            <x-ui.select name="role_id" label="Role approver" required>
                                <option value="">Pilih role</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.select name="permission_id" label="Jenis izin" required>
                                <option value="">Pilih izin</option>
                                @foreach ($permissions as $permission)
                                    <option value="{{ $permission->id }}">{{ $permission->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.input name="minimum_amount" label="Nominal minimum" type="number" min="0" step="0.01" value="0" required />
                            <x-ui.input name="maximum_amount" label="Nominal maksimum" type="number" min="0" step="0.01" hint="Kosongkan jika tanpa batas maksimum." />
                            <x-ui.input name="reason" label="Alasan konfigurasi" required />
                            <x-ui.button type="submit">Simpan batas</x-ui.button>
                        </form>
                    </x-ui.card>

                    <x-ui.card title="Batas aktif" description="Cabang: {{ $currentBranch->name }}">
                        @if ($approvalLimits->isEmpty())
                            <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Belum ada batas persetujuan.</p>
                        @else
                            <x-ui.data-table label="Batas persetujuan aktif">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3" scope="col">Role</th>
                                        <th class="px-4 py-3" scope="col">Izin</th>
                                        <th class="px-4 py-3 text-right" scope="col">Rentang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($approvalLimits as $limit)
                                        <tr>
                                            <td class="px-4 py-3 font-medium">{{ $limit->role->name }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $limit->permission->name }}</td>
                                            <td class="px-4 py-3 text-right tabular-nums">
                                                {{ $formatter->currencyDecimal($limit->minimum_amount) }} –
                                                {{ $limit->maximum_amount === null ? 'Tanpa batas' : $formatter->currencyDecimal($limit->maximum_amount) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.data-table>
                        @endif
                    </x-ui.card>
                </section>
            @endcan
        @endif

        @can('audit.view')
            <x-ui.card title="Audit log" description="Jejak append-only untuk aksi kritis pada cabang aktif.">
                @if ($auditLogs === null || $auditLogs->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Belum ada aktivitas kritis pada cabang aktif.</p>
                @else
                    <x-ui.data-table label="Audit log cabang">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3" scope="col">Waktu</th>
                                <th class="px-4 py-3" scope="col">Aktor</th>
                                <th class="px-4 py-3" scope="col">Aksi</th>
                                <th class="px-4 py-3" scope="col">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($auditLogs as $auditLog)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $formatter->dateTime($auditLog->created_at) }}</td>
                                    <td class="px-4 py-3">{{ $auditLog->actor?->name ?? 'Sistem' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $auditLog->action }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $auditLog->reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>
                    <div class="mt-5">{{ $auditLogs->links() }}</div>
                @endif
            </x-ui.card>
        @endcan
    </div>
</x-layouts.app>
