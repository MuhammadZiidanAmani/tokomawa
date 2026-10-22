# Project Progress — Retail Management System

**Tanggal pembaruan:** 22 Oktober 2026  
**Tahap aktif:** M0.2 — COMPLETED; berhenti sebelum M0.3  
**Status implementasi aplikasi:** M0.2 COMPLETED pada MariaDB 11.8.8/InnoDB; migration, full suite, schema audit, dan concurrency dua proses lulus  
**Gate:** M0.3 tetap terkunci sampai pengguna memberikan instruksi eksplisit **LANJUT**

## 1. Ringkasan status

| Area | Status | Keterangan |
|---|---|---|
| Bootstrap agent/project | Selesai | PHP/Composer diverifikasi; Laravel Boost dipasang; `AGENTS.md` dibaca ulang. |
| Review PRD | Selesai | Seluruh `PRD_Retail_Management_System.md` telah dibaca. |
| Review architecture | Selesai | Seluruh `architecture.md` telah dibaca. |
| Review design | Selesai | Seluruh `design.md` telah dibaca. |
| Audit project/dependency | Selesai | Skeleton, runtime, config, migrasi, dependency, dan test baseline diperiksa. |
| Analisis ketidaksesuaian | Selesai | Pemetaan istilah dan resolusi dicatat di `IMPLEMENTATION_PLAN.md`. |
| Implementation plan | Selesai | Fase F0–F4, milestone, dependensi, tabel/file, acceptance criteria, dan safe transaction order disusun. |
| Implementasi M0.1 | **COMPLETED** | Session auth, modular foundation, Livewire dashboard, design system, user command, audit regresi, dan quality gate telah lulus. |
| Implementasi M0.2 | **COMPLETED** | Delapan migration development, 59 test/325 assertion, audit 24 tabel InnoDB, dan concurrency dua proses untuk sequence/idempotency lulus pada MariaDB. |
| Implementasi modul bisnis | Belum dimulai | Master, purchasing, inventory, POS, keuangan, dan laporan tetap di luar scope M0.2. |

**Interpretasi progres:** planning baseline, M0.1, dan M0.2 selesai. Fondasi organisasi, akses, audit, approval, idempotency, dan nomor dokumen telah tervalidasi pada MariaDB/InnoDB. Seluruh modul bisnis tetap belum dimulai dan M0.3 menunggu instruksi pengguna.

## 2. Baseline lingkungan yang terverifikasi

| Komponen | Nilai/kondisi |
|---|---|
| OS/shell | Windows PowerShell |
| PHP | 8.5.6 |
| Composer | 2.9.8 |
| Laravel | 13.38.0 |
| Laravel Boost | 2.10.3 (development dependency) |
| Livewire | 4.4.8 |
| PHPUnit | 12.5.40 |
| Node.js | 24.15.0 |
| npm | 11.12.1 |
| Database lokal lama | SQLite dipertahankan beserta datanya; tidak lagi menjadi database utama atau bukti concurrency |
| Database target | MariaDB 11.8.8 berjalan sebagai Windows service pada port 3306; PHP `pdo_mysql`/`mysqli` aktif; default engine server InnoDB |
| Locale/timezone saat ini | `id`; internal `UTC`, display `Asia/Jakarta` |
| Target UI/display | Bahasa Indonesia / IDR / Asia/Jakarta |
| Frontend install | Dependency terpasang; `package-lock.json` tersedia; Vite/Tailwind build lulus |
| Version control | Repository Git lokal telah diinisialisasi |

## 3. Kondisi kode setelah M0.1

- Session authentication tersedia untuk login/logout; registrasi publik tidak tersedia.
- Route dashboard dilindungi middleware `auth`; login dilindungi middleware `guest` dan rate limiter pada service.
- Pengguna internal pertama dapat dibuat melalui command interaktif `php artisan retail:user-create`; tidak ada password default.
- Struktur modular telah dimulai pada `app/Domain/Access`, `app/Domain/Shared`, dan `app/Livewire/Dashboard`; modul lain baru dibuat ketika milestone terkait dimulai.
- AppShell, sidebar, topbar, dashboard, dan komponen UI reusable tersedia dengan Tailwind CSS 4 serta Alpine dari Livewire.
- Locale UI `id`, mata uang `IDR`, penyimpanan waktu internal UTC, dan display timezone `Asia/Jakarta` telah dikonfigurasi.
- Belum ada RBAC, branch table, master, stock ledger, AP ledger, pricing engine, POS, atau laporan bisnis.

## 4. Verifikasi yang telah dijalankan

| Pemeriksaan | Hasil |
|---|---|
| `php -v` | Lulus — PHP 8.5.6 |
| `composer -V` | Lulus — Composer 2.9.8 |
| `composer require laravel/boost --dev` | Lulus — Boost 2.10.3 terpasang |
| `php artisan boost:install` | Lulus — guidelines, skills, dan MCP configuration dibuat |
| `composer show --direct` | Lulus — dependency langsung diinventarisasi |
| `composer validate --strict` | Lulus — `composer.json` valid |
| `composer audit` | Lulus — tidak ada security advisory yang ditemukan |
| `php artisan migrate:status` | Lulus — 3 migrasi bawaan sudah dijalankan |
| `php artisan test --compact` | Lulus — 2 test, 2 assertion, 0 failure, sekitar 643 ms |
| `composer require livewire/livewire` | Lulus — Livewire 4.4.8 kompatibel dengan Laravel 13.38.0 |
| `npm install` | Lulus — lockfile dibuat, instalasi awal melaporkan 0 vulnerability |
| `php artisan migrate --pretend` | Lulus — hanya menambah `is_active`, `last_login_at`, dan index users |
| `php artisan migrate --no-interaction` | Lulus — migration M0.1 batch 2 selesai tanpa menghapus data |
| `vendor/bin/pint --dirty --format agent` | Lulus |
| `npm run build` | Lulus — Vite 8.3.5, aset Tailwind/Inter dan manifest berhasil dibuat |
| `php artisan view:cache` | Lulus — seluruh Blade template dapat dikompilasi |
| `php artisan test --compact` setelah M0.1 | Lulus — 19 test, 82 assertion, 0 failure, sekitar 2,9 detik |
| `composer validate --strict` dan `composer audit` | Lulus — metadata valid, 0 advisory |
| `npm audit --offline --json` | Lulus — 0 vulnerability pada cache/lockfile |
| HTTP smoke test lokal | Lulus — `/login` 200 dan memuat form; `/dashboard` guest 302 ke `/login` |
| Audit final M0.1 setelah perbaikan | Lulus — 21 test, 86 assertion, 0 failure, sekitar 5,2 detik |
| Regression test temuan audit | Lulus — 16 test, 78 assertion, 0 failure |
| Route/migration/schema audit | Lulus — root hanya `GET|HEAD`; auth middleware benar; 4 migration `Ran`; users memiliki unique email dan index status |
| Koneksi MariaDB aplikasi | Lulus — Laravel terautentikasi sebagai akun aplikasi terbatas pada `retail_development`; MariaDB 11.8.8, InnoDB, isolation `REPEATABLE-READ` |
| PHP database driver | Lulus — `pdo_mysql=yes`; PDO drivers memuat `mysql,pgsql,sqlite` |
| Migration MariaDB development | Lulus — dry-run ditinjau, lalu 8 migration forward-only `Ran`; verifikasi berikutnya `Nothing to migrate` |
| Audit schema MariaDB | Lulus — development dan testing masing-masing 24/24 tabel InnoDB, 25 foreign key, 17 unique constraint; unique scope sequence/idempotency terverifikasi |
| Focused transaction/concurrency gate | Lulus — 10 test, 41 assertion; dua proses sequence menghasilkan 50 nomor unik dan idempotency menjalankan side effect tepat sekali |
| Full PHPUnit suite MariaDB | Lulus — 59 test, 325 assertion, 0 failure pada `retail_testing` |

Catatan: kelulusan test membuktikan acceptance M0.1, bukan modul bisnis retail.

## 5. Artefak yang dibuat/diubah pada tahap awal

### Diminta pengguna

- `IMPLEMENTATION_PLAN.md` — rencana implementasi dan resolusi ketidaksesuaian.
- `PROJECT_PROGRESS.md` — ledger progres dan bukti verifikasi.

### Bootstrap Laravel Boost

- `composer.json` dan `composer.lock` — menambahkan `laravel/boost` sebagai development dependency beserta dependency transitif.
- `AGENTS.md` dan `CLAUDE.md` — panduan Laravel Boost hasil installer.
- `boost.json`, `.mcp.json`, `.agents/skills/**`, `.codex/**`, dan `.claude/**` — konfigurasi/skill agent hasil installer.

Tidak ada migration domain, model retail, controller, action, service, route fitur, atau view aplikasi retail yang dibuat.

### Implementasi M0.1

- Backend: auth controller/request/service, user provisioning command, formatter Indonesia, middleware wiring, route protection, dan konfigurasi retail.
- Database: migration additive status akun serta last login.
- Frontend: Livewire dashboard, guest/app layouts, sidebar/topbar, dan komponen button/input/card/table/modal/badge/alert.
- Tooling: repository Git, Livewire Composer dependency, dependency frontend, dan npm lockfile.
- Tests: authentication, rate limiting, inactive user, logout, protected dashboard, user command, formatter IDR/WIB, health, dan Livewire dashboard.

## 6. Register keputusan

| ID | Keputusan | Status | Rekomendasi saat ini |
|---|---|---|---|
| DEC-001 | Database utama development dan production | APPROVED | MariaDB dengan InnoDB; `retail_development` dan `retail_testing` dipisahkan |
| DEC-002 | Metode HPP | APPROVED | Moving Weighted Average |
| DEC-003 | Negative stock | APPROVED | Dilarang untuk transaksi operasional normal |
| DEC-004 | Cabang/gudang/register awal | PARTIAL | Satu cabang awal dan multi-cabang-ready disetujui; jumlah gudang/register belum ditentukan |
| DEC-005 | Auth/RBAC dependency | APPROVED | Session auth dan RBAC app-owned berbasis Gate/Policy telah diterapkan; MFA privileged tetap scope hardening |
| DEC-006 | PPN dan pembulatan | OPEN | Effective-dated configuration, tanpa tarif hardcoded, validasi akuntan |
| DEC-007 | Approval matrix dan limit | OPEN | Ikuti baseline design lalu sahkan nominal per role |
| DEC-008 | Offline POS | APPROVED | MVP online-required, tidak ada offline sync |
| DEC-009 | Cetak MVP | OPEN | Browser print 58/80 mm |
| DEC-010 | QRIS/EDC | OPEN | Ditunda ke F2 setelah provider/alur dipilih |
| DEC-011 | Settlement konsinyasi | OPEN | Perlu keputusan kontrak |
| DEC-012 | Device dan volume | OPEN | Perlu data perangkat, SKU, transaksi/hari, kasir simultan |

## 7. Status milestone

| Milestone | Status | Bukti/komentar |
|---|---|---|
| Planning baseline | **Selesai** | Tiga dokumen dibaca, proyek diaudit, dua dokumen perencanaan dibuat. |
| M0.1 Baseline teknis, modular structure, auth, design system | **COMPLETED** | Audit final: 21 test/86 assertion lulus, build lulus, migration additive ran, auth/UI berfungsi. |
| M0.2 Organization, RBAC, approval, audit, idempotency, sequence | **COMPLETED** | Migration/full suite/schema audit/concurrency lulus pada MariaDB/InnoDB; bukti final tercatat pada bagian 16. |
| M0.3 Master/catalog/supplier/pricing/tax | Belum dimulai | Bergantung M0.2. |
| M1.1 Stock ledger, balance, costing | **Belum dimulai** | DEC-002/003 sudah disahkan; masih bergantung pada M0.2 dan M0.3. |
| M1.2 Purchase Order | Belum dimulai | Bergantung fondasi/master. |
| M1.3 Goods receipt | Belum dimulai | Bergantung PO dan stock ledger. |
| M1.4 Purchase invoice/AP minimum | Belum dimulai | Bergantung receipt. |
| M1.5 Shift/POS cash | Belum dimulai | Bergantung stock ledger, pricing dasar, register. |
| M1.6 Receipt/report/EOD minimum | Belum dimulai | Bergantung invoice dan POS cash. |
| F2 Operations/reversal | Belum dimulai | Dikerjakan satu milestone per instruksi. |
| F3 Marketing/loyalty | Belum dimulai | Dikerjakan setelah F2 gate terkait. |
| F4 Analytics/hardening/go-live | Belum dimulai | Dikerjakan setelah transaksi inti stabil. |

## 8. Masalah dan risiko terbuka

1. MariaDB 11.8.8 dan driver PHP tersedia; aplikasi memakai akun khusus `retail_app`, bukan root, dengan scope hanya `retail_development` dan `retail_testing`.
2. `.env` lokal diarahkan ke koneksi native Laravel `mariadb` dan `retail_development`; `phpunit.xml` mengisolasi test ke `retail_testing`; secret tidak dicatat dalam dokumen atau output aplikasi.
3. Pengguna pertama belum dibuat pada database development karena password tidak boleh dibuat atau di-hardcode oleh agent.
4. `SESSION_SECURE_COOKIE=false` diperlukan untuk HTTP lokal; production HTTPS wajib mengaturnya menjadi `true`.
5. Build memberi warning opsional `fontaine` untuk optimized font fallback, tetapi build berhasil dan aset Inter terbentuk.
6. MFA privileged, jumlah gudang/register operasional, PPN, rounding, nominal approval bisnis, perangkat, dan volume masih perlu diputuskan pada milestone terkait. Struktur RBAC dan konfigurasi approval limit sudah tersedia tanpa nominal hardcoded.
7. Repository Git sudah diinisialisasi, tetapi baseline proyek belum memiliki commit dan seluruh file masih berstatus untracked; pembuatan commit tetap menjadi keputusan pengguna dan bukan blocker acceptance M0.1.

## 9. Protokol pembaruan milestone

Untuk setiap milestone berikutnya, tambahkan catatan dengan format:

```text
### [ID] Nama milestone — YYYY-MM-DD
Status: IN_PROGRESS | BLOCKED | PASSED | COMPLETED
Tujuan:
File yang dibuat/diubah:
Tabel/migration:
Test yang dijalankan:
Hasil test:
Masalah/risiko:
Keputusan baru:
Acceptance criteria:
Next gate: STOP — menunggu LANJUT
```

## 10. Aksi berikutnya

STOP setelah M0.2 COMPLETED. Jangan memulai M0.3 sebelum pengguna memberikan instruksi eksplisit **LANJUT**.

## 11. Audit pembukaan M1.1 — 22 Oktober 2026

**Status:** BLOCKED sebelum coding

**Pekerjaan M1.1 yang direncanakan bila dependency terpenuhi:**

- Migration dan model `inventory_batches`, `inventory_movements`, `inventory_balances`, `stock_adjustments`, dan `stock_adjustment_items`.
- Action/service posting stok atomik dengan row lock, idempotency, costing, audit, dan balance projection.
- Controller/FormRequest/policy dan UI Bahasa Indonesia untuk opening balance yang melalui approval.
- Reconciliation service/command serta feature, integration, rollback, retry, dan concurrency tests.

**Bukti dependency belum terpenuhi:**

- Hanya tiga migration bawaan Laravel yang tersedia dan berstatus `Ran`.
- `app/Domain`, `app/Livewire`, dan `app/Policies` belum ada.
- Tidak ada migration/model untuk `branches`, `warehouses`, `products`, roles/permissions, `audit_logs`, `approval_requests`, `idempotency_keys`, atau `document_sequences`.
- Direct dependency belum mencakup Livewire, auth scaffolding, atau RBAC.
- Database lokal masih SQLite, sehingga tidak dapat menjadi bukti concurrency/row-locking produksi.
- DEC-002 (metode HPP) dan DEC-003 (negative stock) masih `OPEN`.

**File aplikasi/migration yang dibuat:** tidak ada.  
**Migration yang dijalankan:** tidak ada migration baru.  
**Test fitur yang dijalankan:** tidak ada karena fitur tidak dibuat.  
**Alasan berhenti:** membuat M1.1 sekarang akan memerlukan foreign key palsu/nullable atau sekaligus membangun M0.1–M0.3, yang melanggar urutan dependency dan aturan satu milestone per instruksi.

## 12. M0.1 — Baseline teknis, modular structure, auth, dan design system — 22 Oktober 2026

**Status:** COMPLETED

**Tujuan:** menyediakan fondasi Laravel modular, session authentication internal, design system, dashboard, konfigurasi Indonesia, dan quality gate tanpa membangun modul bisnis.

**File/area utama yang dibuat atau diubah:**

- `app/Domain/Access/Services/SessionAuthenticationService.php`
- `app/Domain/Shared/Support/IndonesiaFormatter.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Console/Commands/CreateUserCommand.php`
- `app/Livewire/Dashboard/Overview.php`
- `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, dan `bootstrap/app.php`
- `config/retail.php`, `config/app.php`, `.env.example`, dan konfigurasi non-secret lokal `.env`
- `routes/web.php`, `lang/id/**`, `resources/css/app.css`, `resources/js/app.js`, dan `vite.config.js`
- Layout, auth view, dashboard, sidebar/topbar, serta komponen UI di `resources/views/**`
- Test auth, dashboard, command pengguna, formatter, health, dan Livewire di `tests/**`
- `composer.json`, `composer.lock`, `package-lock.json`; repository Git lokal diinisialisasi.

**Database/migration:**

- Migration `2026_10_22_023950_add_account_status_to_users_table` menambah `is_active` (default true + index) dan `last_login_at` nullable.
- `--pretend` dikaji sebelum migration aktual.
- Migration berjalan pada SQLite development sebagai batch 2 tanpa delete/drop data.
- PostgreSQL belum dimigrasikan karena tooling/server/extension/kredensial lokal tidak tersedia.

**Hasil test dan quality gate:**

- Targeted auth/UI/service tests: 16 test, 73 assertion — PASSED.
- Full suite final: 19 test, 82 assertion, 0 failure — PASSED.
- Laravel Pint — PASSED.
- Blade compile — PASSED.
- Vite/Tailwind production build — PASSED.
- Composer validate/audit — PASSED, 0 advisory.
- npm install dan audit offline lockfile — PASSED, 0 vulnerability.
- Route list hanya memuat root redirect, login, logout, dan protected dashboard untuk aplikasi M0.1.
- Smoke test server development — `/login` HTTP 200; dashboard guest redirect ke login; server sementara dihentikan setelah verifikasi.

**Acceptance criteria:**

- [x] Login/logout berbahasa Indonesia dan session regeneration/invalidation diterapkan.
- [x] Guest ditolak dari dashboard; authenticated user dialihkan dari login.
- [x] Registrasi publik tidak tersedia.
- [x] Login dibatasi 5 percobaan per email+IP per menit.
- [x] Akun inactive tidak dapat login dan last login dicatat untuk login berhasil.
- [x] Pengguna internal dapat dibuat secara interaktif tanpa password default.
- [x] AppShell, sidebar, topbar, dashboard, responsive layout, dan reusable UI tersedia.
- [x] IDR dan waktu WIB diformat serta diuji, sementara internal timezone tetap UTC.
- [x] Tidak ada master, stock ledger, transaksi, atau data KPI bisnis palsu yang dibuat.

**Masalah/risiko tersisa:** PostgreSQL development belum dapat diaktifkan pada mesin ini; user pertama harus dibuat oleh operator; HTTPS production harus memakai secure cookie; RBAC/MFA berada di milestone berikutnya/hardening.

**Next gate:** STOP — menunggu **LANJUT M0.2**.

## 13. Audit final M0.1 — 22 Oktober 2026

**Status:** COMPLETED

**Cakupan audit:** PRD, architecture, design, implementation plan, progress ledger, dependency, konfigurasi, migration/schema, authentication, authorization dasar, routes, dashboard, komponen UI, dan automated tests.

**Temuan dan perbaikan:**

1. `DatabaseSeeder` bawaan masih membuat akun dummy dengan password factory. Seeder diubah menjadi no-op; akun internal hanya dibuat melalui `php artisan retail:user-create`. Regression test memastikan seeding tidak membuat akun default.
2. Route `/` terdaftar sebagai `ANY` melalui `Route::redirect`. Route dibatasi menjadi `GET|HEAD`; regression test memastikan request `POST /` ditolak dengan 405.
3. Drawer navigasi tablet belum menjamin focus trap/restore focus. Implementasi diganti ke native `<dialog>` dengan tombol tutup, Escape/backdrop close, dan perilaku fokus browser.
4. `IMPLEMENTATION_PLAN.md` masih memiliki status dan next gate historis yang tidak sesuai kondisi setelah M0.1. Status diselaraskan menjadi `COMPLETED`; titik mulai berikutnya menjadi M0.2 hanya setelah instruksi eksplisit.

**Bukti quality gate setelah perbaikan:**

- Full suite: 21 test, 86 assertion, 0 failure — PASSED.
- Targeted auth/route/seeder/UI regression: 16 test, 78 assertion — PASSED.
- Pint: PASSED.
- Blade compile: PASSED.
- Vite/Tailwind production build: PASSED.
- `composer validate --strict`: PASSED.
- Composer audit dan npm audit offline: 0 vulnerability.
- Migration dry-run: tidak ada migration tertunda; empat migration development berstatus `Ran`.
- Database development tetap SQLite dengan 0 user; PostgreSQL belum dapat diuji karena CLI/server dan extension PHP pgsql belum tersedia.

**Authorization dalam scope M0.1:** route dashboard/logout dilindungi middleware `auth`, route login dilindungi `guest`, dan pengujiannya lulus. RBAC/policy/cabang bukan klaim M0.1 dan tetap menjadi scope M0.2.

**Error tersisa dalam scope M0.1:** tidak ada. Warning build `fontaine` bersifat opsional dan tidak memengaruhi aset atau runtime.

**Next gate:** STOP — M0.2 belum dimulai; menunggu **LANJUT M0.2**.

## 14. M0.2 — Organisasi, RBAC, approval, audit, idempotency, dan nomor dokumen — 22 Oktober 2026

**Status:** COMPLETED — seluruh acceptance criteria lulus pada MariaDB/InnoDB.

**Tujuan:** menyediakan scope organisasi multi-cabang-ready, authorization server-side, approval limit, audit append-only, serta primitif atomis/idempotent sebelum modul master dan transaksi.

**Fitur yang berfungsi:**

- Cabang, gudang, register, pemilihan cabang aktif, serta penolakan akses lintas cabang dengan respons 404.
- Matriks 8 role dan 12 permission melalui Gate, Policy, middleware, direct permission override, dan seeder idempotent.
- Penetapan role/cabang pengguna dengan alasan audit, proteksi self-lockout Super Admin, proteksi eskalasi role Super Admin, dan isolasi daftar pengguna per cabang.
- Konfigurasi batas approval per cabang/role/permission serta service request/approve dengan row lock dan validasi nominal.
- Audit log append-only pada model dengan actor, snapshot role, branch, reason, IP/user-agent, before/after snapshot, dan masking secret rekursif.
- Reusable idempotency service yang mereplay respons untuk key/payload sama, menolak payload berbeda, menolak request yang masih processing, dan rollback claim pada kegagalan.
- Nomor dokumen per cabang/jenis/periode dengan unique constraint, transaction, `lockForUpdate`, deadlock retry, format timezone cabang, dan validasi input.
- Transactional outbox recorder serta event organisasi saat cabang dibuat.
- Halaman `/pengaturan` responsif berbahasa Indonesia untuk organisasi, akses, approval limit, dan audit; sidebar/topbar serta komponen select reusable diperbarui.

**File/area utama yang dibuat atau diubah:**

- Domain: `app/Domain/Organization/**`, `app/Domain/Access/**`, `app/Domain/Shared/{DTOs,Exceptions,Models,Services}/**`.
- HTTP/security: controller `app/Http/Controllers/Settings/**`, current-branch controller, Form Request settings, middleware branch/permission/current context, policies, provider, routes, dan `User` relations/helpers.
- UI: `resources/views/settings/governance.blade.php`, app layout, sidebar, serta `resources/views/components/ui/select.blade.php`.
- Data/reference: `config/access.php`, `database/seeders/AccessControlSeeder.php`, factory M0.2, dan penyesuaian `DatabaseSeeder`/command user pertama.
- Test: permission matrix, settings HTTP wiring, approval/audit/idempotency/document sequence service, seeder/command regression, formatter, serta concurrency dua proses untuk sequence dan idempotency.

**Database/migration:**

- Batch 3 `Ran`: `2026_10_22_032821_create_m0_2_01_organization_tables`.
- Batch 3 `Ran`: `2026_10_22_032824_create_m0_2_02_access_control_tables`.
- Batch 3 `Ran`: `2026_10_22_032827_create_m0_2_03_approval_and_audit_tables`.
- Batch 3 `Ran`: `2026_10_22_032830_create_m0_2_04_reliability_tables`.
- Tabel baru: `branches`, `warehouses`, `registers`, `user_branches`, `roles`, `permissions`, `permission_role`, `role_user`, `permission_user`, `approval_limits`, `approval_requests`, `audit_logs`, `idempotency_keys`, `document_sequences`, dan `outbox_events`.
- Seeder reference data dijalankan pada SQLite development: 8 role, 12 permission, 0 user, 0 cabang. Seeder tidak membuat akun atau transaksi dummy.
- MariaDB `retail_development`: seluruh 8 migration berjalan forward-only tanpa reset; 24 tabel terbentuk dan seluruhnya InnoDB.
- MariaDB `retail_testing`: schema di-reset hanya oleh test, terpisah dari development; 8 migration dan 24 tabel InnoDB terverifikasi.

**Hasil test dan quality gate:**

- Targeted approval/idempotency/sequence/isolasi akses: 22 test, 63 assertion — PASSED.
- Targeted organisasi dan approval limit HTTP: 6 test, 35 assertion — PASSED.
- Full suite final setelah regresi bootstrap cabang pertama: 57 test, 302 assertion — PASSED.
- Pint — PASSED; Blade cache — PASSED; Vite/Tailwind production build — PASSED.
- `composer validate --strict` — PASSED; Composer audit dan npm audit offline — 0 vulnerability.
- Seluruh 8 migration development berstatus `Ran` dan skema diverifikasi melalui koneksi aplikasi SQLite.
- Warning build opsional `fontaine` tetap tidak memengaruhi build atau runtime.
- MariaDB focused reliability gate: 10 test, 41 assertion — PASSED.
- MariaDB full suite: 59 test, 325 assertion, 0 failure — PASSED.
- Schema audit: development/testing masing-masing 24 tabel InnoDB, 25 foreign key, 17 unique constraint; unique key `document_sequences` dan `idempotency_keys` sesuai scope.
- Development safety: hanya migration forward-only; tidak ada `migrate:fresh`, rollback, drop database, seeding, atau perubahan data development. Setelah migration, 0 user, 0 cabang, dan 0 sequence tetap tercatat.

**Acceptance criteria:**

- [x] Akses lintas cabang ditolak tanpa membocorkan keberadaan record; HTTP test mengharapkan 404.
- [x] Matriks permission diuji melalui Gate/Policy dan HTTP wiring untuk organisasi, akses, serta approval limit.
- [x] Dua proses independen menghasilkan 50 nomor unik berurutan tanpa duplikasi pada MariaDB/InnoDB.
- [x] Retry idempotency key mengembalikan hasil sama; key sama dengan payload berbeda ditolak.
- [x] Dua proses independen dengan idempotency key/payload sama mengeksekusi callback/outbox tepat sekali; proses kedua mereplay response yang sama.
- [x] Audit aksi kritis menyimpan actor, branch, reason, snapshot role/before/after, dan secret termasking.

**Masalah/risiko tersisa:** tidak ada blocker dalam scope M0.2. Nominal approval bisnis tetap data konfigurasi dan tidak di-hardcode; keputusan PPN, perangkat, volume, dan modul transaksi tetap berada pada milestone berikutnya.

**Next gate:** STOP — M0.3 terkunci sampai instruksi eksplisit **LANJUT**.

## 15. Audit blocker PostgreSQL M0.2 — 22 Oktober 2026 (historis)

**Status:** SUPERSEDED — catatan ini dipertahankan sebagai riwayat; DEC-001 kini memilih MariaDB/InnoDB dan tidak mengharuskan bootstrap PostgreSQL.

**Hasil pemeriksaan read-only:**

- PHP CLI 8.5.6 memakai `C:\php\php.exe` dan memuat `C:\php\php.ini`.
- DLL `php_pdo_pgsql.dll` dan `php_pgsql.dll` tersedia di `C:\php\ext`; keduanya telah diaktifkan secara minimal pada `C:\php\php.ini`.
- Verifikasi PHP CLI lulus: `pdo_pgsql=yes`, `pgsql=yes`, dan PDO drivers berisi `mysql,pgsql,sqlite`.
- PostgreSQL 17.11-5 tercatat terpasang melalui winget; `psql (PostgreSQL) 17.11` tersedia di `C:\Program Files\PostgreSQL\17\bin\psql.exe`.
- Installer gagal menginisialisasi cluster dan service `postgresql-x64-17`; port 5432 tidak listening dan direktori data tidak memiliki `PG_VERSION`.
- Log installer mencatat `postgres.exe` diblokir kebijakan Device Guard. Event Code Integrity 3033/3077 mengonfirmasi biner tidak memenuhi Enterprise signing level pada Policy ID `{0283ac0f-fff1-49ae-ada1-8a933130cad6}`.
- Audit terbaru mengidentifikasi policy tersebut sebagai `VerifiedAndReputableDesktop` dalam mode enforcement (Smart App Control), bukan policy domain; komputer berada pada `WORKGROUP` dan tidak domain-joined. Sumber MDM/administrator policy tidak dapat dipastikan tanpa akses administrator.
- Kondisi terbaru berbeda dari kegagalan installer: `postgres.exe --version` dan `initdb.exe --version` sekarang sama-sama berhasil dengan versi 17.11. Tidak ada event blokir baru dari pemeriksaan tersebut.
- Biner berasal dari paket winget `PostgreSQL.PostgreSQL.17` versi 17.11-5 dengan URL installer EnterpriseDB dan SHA-256 manifest `612c7f5400a003aaffa0372cb273eed52278f93c680ab911eb9cb2229e329072`. Metadata file menunjukkan versi 17.11 dan publisher PostgreSQL Global Development Group; executable individual tidak memiliki tanda tangan Authenticode.
- Docker, Podman, dan WSL tidak tersedia sebagai alternatif server terisolasi.
- Winget menyediakan paket resmi `PostgreSQL.PostgreSQL.17` versi 17.11-5.

**Perubahan yang dilakukan:** paket resmi PostgreSQL 17.11-5 terpasang dan `C:\php\php.ini` hanya diubah untuk mengaktifkan `extension=pdo_pgsql` serta `extension=pgsql`. Tidak ada perubahan `.env`, database PostgreSQL, migration, atau test PostgreSQL karena server tidak dapat berjalan. SQLite development dan datanya tidak diubah.

**Verifikasi setelah temuan:** regression suite SQLite tetap lulus 57 test/302 assertion dan Laravel Pint lulus. Ini hanya membuktikan tidak ada regresi; bukan pengganti migration/test/concurrency PostgreSQL.

**Rencana setelah disetujui:** buka PowerShell ter-elevasi yang interaktif, jalankan `initdb` sebagai pemilik cluster dengan autentikasi SCRAM dan prompt password langsung, daftarkan `postgresql-x64-17` sebagai Windows service menggunakan `pg_ctl`, lalu verifikasi service/port. Setelah itu buat database development/test terpisah, konfigurasi secret lokal yang diabaikan Git, jalankan migration/full suite, dan uji concurrency dengan minimal dua proses PHP independen.

**Next gate historis:** tidak berlaku setelah perubahan DEC-001 ke MariaDB. PostgreSQL dan seluruh data yang ada tetap dipertahankan.

## 16. Audit final MariaDB M0.2 — 22 Oktober 2026

**Status:** COMPLETED

**Environment dan koneksi:**

- Laravel 13.38.0 memakai koneksi native `mariadb` ke MariaDB 11.8.8 dengan InnoDB dan isolation level `REPEATABLE-READ`.
- PHP CLI memuat `pdo_mysql`; akun aplikasi yang terautentikasi adalah `retail_app@localhost`, bukan root.
- Hak akun aplikasi dibatasi ke `retail_development` dan `retail_testing`.
- `.env` menargetkan `retail_development`; `phpunit.xml` menimpa database menjadi `retail_testing`. Password tidak dicatat di repository maupun ledger progres.

**Migration dan schema:**

- `php artisan migrate --pretend --no-interaction` ditinjau lebih dahulu dan menghasilkan DDL InnoDB tanpa operasi drop.
- `php artisan migrate --no-interaction` menjalankan seluruh 8 migration secara forward-only pada development.
- `php artisan migrate:status` menunjukkan seluruh 8 migration `Ran`; dry-run berikutnya menghasilkan `Nothing to migrate`.
- `retail_development` dan `retail_testing` masing-masing memiliki 24 base table; 24/24 memakai InnoDB, tanpa engine non-transaksional.
- Masing-masing schema memiliki 25 foreign key dan 17 unique constraint.
- Unique index kritis terverifikasi: sequence pada `(branch_id, document_type, year, month)`, idempotency pada `(scope, operation, key)`, serta approval request pada `idempotency_key`.
- Development tidak di-reset atau di-seed; setelah migration tetap 0 user, 0 cabang, dan 0 document sequence. SQLite, PostgreSQL, dan database lain tidak dihapus atau diubah.

**Transaction dan concurrency:**

- Generator nomor dokumen memakai transaction dengan bounded retry, unique constraint, `insertOrIgnore`, lalu `lockForUpdate` sebelum increment.
- Idempotency memakai transaction dengan bounded retry, unique claim, dan `lockForUpdate`; replay hanya terjadi setelah status `completed` tersimpan.
- Test dua proses PHP independen untuk sequence menghasilkan 50 nomor unik berurutan dari `000001` sampai `000050`, dengan `last_number = 50`.
- Test dua proses PHP independen untuk idempotency menghasilkan satu claim dan satu outbox side effect; satu proses mengeksekusi operasi dan proses lain mereplay payload/response code yang sama.

**Pengujian dan perbaikan:**

- Targeted concurrency sequence: 1 test, 11 assertion — PASSED.
- Targeted concurrency idempotency: 1 test, 12 assertion — PASSED.
- Full suite pertama mengungkap state test concurrency tertinggal: 57/59 test lulus dan 2 test gagal karena data sisa, bukan karena race condition aplikasi.
- Kedua test concurrency diperbaiki agar selalu membersihkan kembali hanya `retail_testing` dalam `finally`, setelah guard memastikan nama database testing.
- Regression terarah setelah perbaikan: 12 test, 67 assertion — PASSED.
- Focused document sequence/idempotency final: 10 test, 41 assertion — PASSED.
- Full suite final: 59 test, 325 assertion, 0 failure — PASSED.
- Laravel Pint untuk perubahan PHP — PASSED.

**Acceptance criteria final:**

- [x] Koneksi Laravel melalui akun aplikasi khusus berhasil pada MariaDB.
- [x] Development dan testing terpisah; operasi reset hanya terjadi pada testing.
- [x] Seluruh migration development berjalan tanpa reset/drop.
- [x] Semua tabel transaksional dan pendukung memakai InnoDB.
- [x] Foreign key, index, dan unique constraint kritis tersedia.
- [x] Transaction rollback, replay/conflict idempotency, row locking, dan deadlock retry tercakup test.
- [x] Concurrency dua proses untuk document sequence dan idempotency lulus tanpa duplikasi atau side effect ganda.
- [x] Full PHPUnit suite lulus pada MariaDB testing.

**Next gate:** STOP — M0.2 COMPLETED. Jangan memulai M0.3 sebelum instruksi eksplisit **LANJUT** dari pengguna.
