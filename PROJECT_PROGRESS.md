# Project Progress — Retail Management System

**Tanggal pembaruan:** 23 Oktober 2026
**Tahap aktif:** M1.3 — COMPLETED; berhenti sebelum M1.4
**Status implementasi aplikasi:** M1.3 COMPLETED dan ter-deploy lokal pada MariaDB 11.8/InnoDB; migration batch 5 `Ran` pada `retail_development`, sedangkan full regression dan concurrency dua proses lulus pada `retail_testing`
**Gate:** M1.4 tetap terkunci sampai pengguna memberikan instruksi eksplisit **LANJUT**

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
| Implementasi M0.3 | **COMPLETED** | Master katalog, supplier, unit/konversi, lokasi, harga efektif, dan pajak configurable telah berjalan; 82 test/578 assertion lulus. |
| Implementasi M1.1 | **COMPLETED** | Stock ledger immutable, saldo per gudang/batch, MWA, opening balance approval, transfer internal, dan rekonsiliasi lulus 101 test/760 assertion serta concurrency dua proses. |
| Implementasi M1.2 | **COMPLETED** | Purchase Order draft/workflow, snapshot, approval limit, audit, idempotency, dan nomor PO concurrency-safe lulus 122 test/952 assertion full suite. |
| Implementasi M1.3 | **COMPLETED** | Draft dan posting Goods Receipt parsial/penuh, outstanding PO, batch/lot/expiry, RBAC, audit, idempotency, serta concurrency telah lulus pada MariaDB/InnoDB. |

**Interpretasi progres:** planning baseline, M0.1–M0.3, M1.1, M1.2, dan M1.3 selesai. Fondasi organisasi/reliabilitas, master data, ledger/proyeksi stok, Purchase Order, serta Goods Receipt telah tervalidasi pada MariaDB/InnoDB. Purchase Invoice/AP, POS, dan keuangan belum dimulai; M1.4 menunggu instruksi pengguna.

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
| Migration M0.3 development | Lulus — 4 migration forward-only batch 2 `Ran` pada `retail_development`; tidak ada reset, rollback, drop, atau penghapusan data |
| Audit schema M0.3 | Lulus — 16/16 tabel M0.3 memakai InnoDB; unique index, FK `RESTRICT`, serta presisi DECIMAL diverifikasi pada `retail_testing` |
| Targeted master data suite | Lulus — 19 test, 116 assertion untuk barang, hierarki, unit, supplier, lokasi, harga, pajak, RBAC, branch isolation, dan audit |
| Schema integration suite M0.3 | Lulus — 4 test, 49 assertion langsung terhadap `information_schema` MariaDB testing |
| Full regression suite setelah M0.3 | Lulus — 82 test, 578 assertion, 0 failure pada `retail_testing` |
| Regression concurrency M0.2 setelah M0.3 | Lulus — 2 test, 23 assertion; dua proses independen untuk sequence dan idempotency tetap aman |
| Quality gate M0.3 | Lulus — Laravel Pint, Blade cache, 36 route master, dan Vite/Tailwind production build |
| Migration M1.1 development | Lulus — migration forward-only batch 3 `Ran`; seluruh 13 migration berstatus `Ran` dan dry-run berikutnya `Nothing to migrate` |
| Targeted inventory dan schema suite | Lulus — 20 test, 127 assertion untuk posting, MWA, transfer, rollback, immutable ledger, rekonsiliasi, HTTP/RBAC/branch isolation, batch, dan skema MariaDB |
| Concurrency stok terakhir M1.1 | Lulus — 1 test, 10 assertion; dua proses PHP independen memperebutkan satu unit, tepat satu berhasil dan saldo tidak pernah negatif |
| Full regression suite setelah M1.1 | Lulus — 101 test, 760 assertion, 0 failure pada `retail_testing` |
| Quality gate M1.1 | Lulus — Laravel Pint, Blade cache, 8 route persediaan, dan Vite/Tailwind production build |
| Migration M1.2 development | Lulus — dry-run DDL ditinjau, migration batch 4 berjalan forward-only; seluruh 14 migration berstatus `Ran`, tanpa reset/drop/data dummy |
| Audit schema M1.2 | Lulus — `purchase_orders` dan `purchase_order_items` InnoDB; 13 foreign key, 7 unique index, query index, serta seluruh kolom uang/qty fixed DECIMAL terverifikasi |
| Targeted Purchase Order suite | Lulus — 21 test, 136 assertion untuk draft/edit, validation, snapshot, presisi, rollback, workflow, RBAC, branch isolation, audit, idempotency, dan concurrency |
| Concurrency nomor PO M1.2 | Lulus — 1 test, 11 assertion; dua proses PHP independen menghasilkan `PO/2026/10/000001` dan `000002` tanpa duplikasi |
| Full regression suite setelah M1.2 | Lulus — 122 test, 952 assertion, 0 failure pada `retail_testing` |
| Quality gate M1.2 | Lulus — Laravel Pint, Blade cache, 11 route purchasing, dan Vite/Tailwind production build |
| Migration dry-run M1.3 | Lulus — DDL dua tabel InnoDB, foreign key, unique constraint, dan index ditinjau tanpa menerapkan perubahan sebelum persetujuan deployment |
| Deployment lokal M1.3 | Lulus — setelah persetujuan eksplisit, migration berjalan forward-only sebagai batch 5 pada `retail_development`; seluruh 15 migration berstatus `Ran` |
| Audit schema development M1.3 | Lulus — `goods_receipts` dan `goods_receipt_items` InnoDB, 12 foreign key, 8 unique constraint, 0 data awal; akun, Super Admin, cabang, gudang, PO, dan inventory tidak berubah |
| Smoke akses Penerimaan Barang | Lulus — 8 route terdaftar; HTTP authorization/UI suite lulus 4 test, 20 assertion pada `retail_testing` |
| Targeted suite M1.3 | Lulus — 12 test, 117 assertion, 0 failure pada MariaDB `retail_testing` |
| Concurrency M1.3 | Lulus — dua proses PHP independen menghasilkan nomor GR unik; dua post atas outstanding terakhir menghasilkan tepat satu sukses tanpa stok ganda |
| Full regression suite setelah M1.3 | Lulus — 134 test, 1.109 assertion, 0 failure pada `retail_testing` |
| Schema audit M1.3 | Lulus — 2 tabel InnoDB, 8 unique constraint bernama, 9 foreign key transaksi `RESTRICT`, dan kolom qty/uang/cost bertipe `DECIMAL` |
| Quality gate M1.3 | Lulus — Laravel Pint, Blade cache, audit 8 route Goods Receipt, dan Vite/Tailwind production build |

Catatan: bukti terbaru membuktikan acceptance M0.1, M0.2, M0.3, M1.1, M1.2, dan M1.3; belum membuktikan Purchase Invoice/AP, POS, atau keuangan.

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

### Implementasi M0.3

- Domain/backend: model, enum, factory, service transaction, policy, Form Request, dan controller pada `app/Domain/{Catalog,Suppliers,Pricing}/**`, `app/Policies/**`, dan `app/Http/**/Master/**`.
- Database: empat migration forward-only untuk 16 tabel master katalog, supplier, lokasi, harga, riwayat harga, dan aturan pajak.
- Frontend: halaman Barang, Departemen/Kategori/Subkategori/Merek, Satuan, Supplier dan relasi barang, Rak/Lokasi, Strata Harga, serta Konfigurasi Pajak; sidebar dan komponen textarea diperbarui.
- Security/reliability: 11 permission M0.3 ditambahkan ke matriks 8 role; mutasi multi-tabel memakai transaction, row lock pada record utama, FK/unique constraint, audit trail, branch scope, dan inaktivasi sebagai pengganti hard delete.
- Tests: HTTP CRUD/inaktivasi, hierarchy integrity, SKU/barcode uniqueness, unit conversion, decimal precision, supplier relation, branch isolation, RBAC, effective-period conflict, tax configuration, audit log, FK, serta direct `information_schema` audit.

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
| DEC-013 | Kewajiban relasi barang-supplier pada PO | OPEN | Default konfigurasi tidak mewajibkan mapping; dapat diaktifkan setelah kebijakan procurement disahkan |
| DEC-014 | Toleransi over-receipt | OPEN | Default M1.3 menolak kuantitas di atas outstanding; tolerance/approval tidak diasumsikan |

## 7. Status milestone

| Milestone | Status | Bukti/komentar |
|---|---|---|
| Planning baseline | **Selesai** | Tiga dokumen dibaca, proyek diaudit, dua dokumen perencanaan dibuat. |
| M0.1 Baseline teknis, modular structure, auth, design system | **COMPLETED** | Audit final: 21 test/86 assertion lulus, build lulus, migration additive ran, auth/UI berfungsi. |
| M0.2 Organization, RBAC, approval, audit, idempotency, sequence | **COMPLETED** | Migration/full suite/schema audit/concurrency lulus pada MariaDB/InnoDB; bukti final tercatat pada bagian 16. |
| M0.3 Master/catalog/supplier/pricing/tax | **COMPLETED** | Empat migration development `Ran`; 82 test/578 assertion, schema audit, Pint, Blade compile, dan frontend build lulus. |
| M1.1 Stock ledger, balance, costing | **COMPLETED** | Migration development, 5 tabel InnoDB, 101 test/760 assertion, concurrency dua proses, Pint, Blade compile, dan frontend build lulus; bukti final pada bagian 18. |
| M1.2 Purchase Order | **COMPLETED** | Dua tabel InnoDB, 21 test/136 assertion targeted, 122 test/952 assertion full suite, concurrency dua proses, Pint/Blade/build lulus; bukti pada bagian 19. |
| M1.3 Goods receipt | **COMPLETED** | Targeted 12 test/117 assertion, full regression 134 test/1.109 assertion, schema audit, dan concurrency dua proses lulus pada MariaDB/InnoDB. |
| M1.4 Purchase invoice/AP minimum | Belum dimulai | Bergantung receipt. |
| M1.5 Shift/POS cash | Belum dimulai | Bergantung stock ledger, pricing dasar, register. |
| M1.6 Receipt/report/EOD minimum | Belum dimulai | Bergantung invoice dan POS cash. |
| F2 Operations/reversal | Belum dimulai | Dikerjakan satu milestone per instruksi. |
| F3 Marketing/loyalty | Belum dimulai | Dikerjakan setelah F2 gate terkait. |
| F4 Analytics/hardening/go-live | Belum dimulai | Dikerjakan setelah transaksi inti stabil. |

## 8. Masalah dan risiko terbuka

1. MariaDB 11.8.8 dan driver PHP tersedia; aplikasi memakai akun khusus `retail_app`, bukan root, dengan scope hanya `retail_development` dan `retail_testing`.
2. `.env` lokal diarahkan ke koneksi native Laravel `mariadb` dan `retail_development`; `phpunit.xml` mengisolasi test ke `retail_testing`; secret tidak dicatat dalam dokumen atau output aplikasi.
3. Database development memiliki 1 user/Super Admin, 1 cabang, dan 1 gudang yang sudah ada; migration M1.3 mempertahankan seluruhnya, tidak membuat PO/receipt contoh, dan tidak mengubah data inventory.
4. `SESSION_SECURE_COOKIE=false` diperlukan untuk HTTP lokal; production HTTPS wajib mengaturnya menjadi `true`.
5. Build memberi warning opsional `fontaine` untuk optimized font fallback, tetapi build berhasil dan aset Inter terbentuk.
6. MFA privileged, jumlah gudang/register operasional, kebijakan final PPN/rounding, nominal approval bisnis, kewajiban mapping barang-supplier, toleransi over-receipt, perangkat, dan volume masih perlu diputuskan pada milestone terkait. DEC-006, DEC-007, DEC-013, dan DEC-014 tetap OPEN.
7. Repository Git sudah memiliki riwayat lokal; pembuatan commit tetap menjadi keputusan pengguna.

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

STOP setelah M1.3 COMPLETED. M1.4 tetap terkunci sampai pengguna memberikan instruksi eksplisit **LANJUT**.

## 11. Audit pembukaan M1.1 — 22 Oktober 2026

**Status:** SUPERSEDED — catatan ini adalah snapshot historis sebelum M0.1–M0.3 tersedia; dependency tersebut kini telah selesai dan M1.1 sudah diimplementasikan.

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

## 17. M0.3 — Master pendukung, katalog, supplier, harga dasar, dan pajak — 22 Oktober 2026

**Status:** COMPLETED — seluruh acceptance criteria M0.3 lulus pada MariaDB/InnoDB.

**Fitur yang berfungsi:**

- Barang dengan SKU unik, barcode utama/alternatif, klasifikasi, hierarki kategori, merek, satuan dasar/beli/jual, konversi presisi, supplier utama, konfigurasi pajak, deskripsi, status aktif, pencarian, filter, pagination, detail, dan edit.
- Departemen, kategori, subkategori, merek, dan satuan dengan validasi parent-child, kode scoped, inaktivasi, serta FK `RESTRICT` untuk mencegah penghapusan referensi yang telah digunakan.
- Supplier beserta data administratif, supplier SKU, harga beli/kontrak presisi, minimum order, lead time, prioritas, dan relasi supplier utama per barang.
- Rak/lokasi scoped ke cabang dan gudang aktif, penempatan barang, rak utama per cabang, serta respons 404 untuk akses record lintas cabang.
- Strata harga dan harga barang per satuan/minimum kuantitas dengan periode efektif, prioritas, currency IDR, validasi overlap, serta immutable `price_change_logs`.
- Aturan pajak effective-dated dengan tarif, mode inclusive/exclusive/exempt, rounding mode, dan scale yang dapat dikonfigurasi. Tidak ada tarif PPN hardcoded dan DEC-006 tetap OPEN untuk kebijakan perhitungan final.
- Seluruh mutasi master penting memakai server-side Form Request, Gate/Policy, transaction dengan bounded retry, row locking saat update penting, audit log, dan status inaktif sebagai jalur aman pengganti hard delete.

**Migration dan tabel:**

- `2026_10_22_073707_create_m0_3_01_reference_and_tax_tables` — `departments`, `categories`, `subcategories`, `brands`, `units`, `tax_rules`, `price_lists`.
- `2026_10_22_073708_create_m0_3_02_catalog_and_supplier_tables` — `suppliers`, `products`, `product_barcodes`, `product_units`, `supplier_products`.
- `2026_10_22_073709_create_m0_3_03_location_tables` — `shelf_locations`, `product_locations`.
- `2026_10_22_073710_create_m0_3_04_pricing_tables` — `product_prices`, `price_change_logs`.
- Keempat migration dijalankan forward-only sebagai batch 2 pada `retail_development`; seluruh 12 migration proyek berstatus `Ran`.
- Audit development membuktikan 16/16 tabel M0.3 memakai InnoDB. Setelah migration/seeder idempoten: 23 permission, 8 role, 0 user, dan 0 cabang; tidak ada data development lama yang dihapus atau di-reset.
- Nilai uang menggunakan `DECIMAL(18,2)`, kuantitas `DECIMAL(18,3)`, faktor konversi `DECIMAL(18,6)`, dan tarif pajak `DECIMAL(7,4)`; tidak ada kolom float untuk domain tersebut.

**File/area utama:**

- Domain: `app/Domain/Catalog/**`, `app/Domain/Suppliers/**`, dan `app/Domain/Pricing/**`.
- HTTP/security: `app/Http/Controllers/Master/**`, `app/Http/Requests/Master/**`, policies M0.3, provider, routes, dan `config/access.php`.
- UI: `resources/views/master/**`, `resources/views/components/ui/textarea.blade.php`, dan navigasi sidebar.
- Data/test: migration/factory M0.3, permission seeder idempoten, test controller master, schema integration test, serta penyesuaian regression test permission count agar mengikuti konfigurasi.

**Pengujian dan quality gate:**

- Targeted HTTP master suite final: 19 test, 116 assertion — PASSED.
- Direct MariaDB schema suite: 4 test, 49 assertion — PASSED; memastikan target `retail_testing`, 16 tabel InnoDB, unique indexes, FK `RESTRICT`, dan presisi DECIMAL.
- Full regression suite final: 82 test, 578 assertion, 0 failure — PASSED pada `retail_testing`.
- Regression concurrency M0.2 terarah: 2 test, 23 assertion — PASSED; dua proses independen untuk document sequence dan idempotency tidak menghasilkan nomor duplikat atau side effect ganda.
- Laravel Pint `--dirty --format agent` — PASSED.
- `php artisan view:cache` — PASSED; 36 route master terdaftar.
- `npm run build` — PASSED dengan Vite 8.3.5 dan Tailwind CSS 4; warning `fontaine` bersifat opsional dan tidak menggagalkan build.

**Perbaikan selama audit:**

1. Residu scaffold pada `ProductControllerTest` dibersihkan sebelum suite dijalankan.
2. Regression test M0.2 yang masih mengasumsikan 12 permission diubah agar mengikuti jumlah permission pada `config/access.php`; matriks aktual berisi 23 permission dan seeder tetap idempoten.
3. Coverage update/inaktivasi ditambahkan untuk barang, satuan, supplier, rak, strata harga, dan aturan pajak agar klaim CRUD tidak hanya bergantung pada route atau implementasi statis.
4. Audit skema otomatis ditambahkan untuk mencegah regresi engine, constraint, dan presisi database target.

**Acceptance criteria final:**

- [x] Seluruh scope M0.3 pada implementation plan diimplementasikan tanpa memulai inventory, purchasing, POS, atau keuangan.
- [x] CRUD melalui aplikasi berfungsi untuk master penting; hard delete sengaja tidak diekspos dan diganti inaktivasi.
- [x] Data tersimpan pada MariaDB; migration development forward-only berhasil tanpa reset/drop.
- [x] RBAC dan branch isolation lulus HTTP test, termasuk penolakan lintas cabang dengan 404.
- [x] Harga, kuantitas, faktor konversi, dan pajak memakai DECIMAL dan diuji tanpa float.
- [x] Foreign key, unique constraint, index, dan InnoDB diverifikasi melalui `information_schema`.
- [x] Audit perubahan master penting, harga, dan pajak tersimpan.
- [x] Seluruh automated test dan regression suite lulus pada `retail_testing`.
- [x] UI responsif berbahasa Indonesia, memakai design system, dan frontend build berhasil.
- [x] Tidak ada blocker kritis tersembunyi; DEC-006 tetap dinyatakan OPEN dan tidak diklaim final.

**Next gate:** STOP — M0.3 COMPLETED. Jangan memulai M1.1 sebelum instruksi eksplisit **LANJUT** dari pengguna.

## 18. M1.1 — Inventory, stock ledger, balance projection, costing, dan opening balance — 23 Oktober 2026

**Status:** COMPLETED — seluruh acceptance criteria M1.1 lulus pada MariaDB/InnoDB.

**Fitur yang berfungsi:**

- `inventory_movements` menjadi ledger posted yang immutable; update dan delete melalui model ditolak.
- `inventory_balances` menjadi proyeksi saldo per cabang, gudang, barang, batch, dan status stok, diperbarui atomik bersama movement.
- Incoming stock menghitung HPP Moving Weighted Average dari nilai persediaan presisi; outgoing stock mempertahankan nilai/HPP secara konsisten tanpa float.
- Opening balance dibuat sebagai stock adjustment, meminta approval terpisah, menolak self-approval, lalu diposting tepat sekali melalui idempotency key.
- Transfer gudang menghasilkan movement keluar/masuk atomik tanpa mengubah total kuantitas atau nilai persediaan.
- Batch dan tanggal kedaluwarsa diwajibkan untuk barang yang dikonfigurasi memakai pelacakan tersebut.
- Reconciliation membandingkan ledger dengan balance projection dan mendeteksi drift kuantitas maupun nilai.
- Dashboard, saldo barang, mutasi, input/persetujuan saldo awal, detail gudang, dan rekonsiliasi tersedia dalam Bahasa Indonesia dengan empty, validation, error, permission, pagination, dan loading states.

**Kontrol transaksi dan keamanan:**

- Seluruh mutasi stok melewati `InventoryPostingService`; controller dan master barang tidak mengubah stok langsung.
- Posting memakai transaction dengan bounded deadlock retry, row lock pada adjustment/balance, unique `source_key`, reusable idempotency service, audit log, dan transactional outbox.
- Constraint/proyeksi `UNSIGNED` serta validasi domain menolak saldo negatif; kegagalan satu baris me-rollback seluruh dokumen.
- Policy, permission, active branch context, dan lookup terscope memberikan 403 untuk permission yang kurang serta 404 untuk record lintas cabang.
- Permission inventory ditambahkan melalui seeder idempoten: 6 permission inventory, 29 permission total; role existing disinkronkan tanpa akun atau transaksi dummy.

**File/area utama:**

- Domain: `app/Domain/Inventory/{Enums,Models,Services}/**`, termasuk posting, submission, approval, decimal arithmetic, dan reconciliation.
- HTTP/security: `app/Http/Controllers/Inventory/**`, `app/Http/Requests/Inventory/**`, `app/Policies/StockAdjustmentPolicy.php`, route persediaan, provider policy, serta matriks `config/access.php`.
- UI: `resources/views/inventory/**`, navigasi sidebar, dan stack script pada AppShell.
- Command/data/test: `app/Console/Commands/ReconcileInventory.php`, factory inventory, migration M1.1, serta empat suite feature/schema/concurrency M1.1.

**Migration dan schema:**

- `2026_10_22_120000_create_m1_1_inventory_tables` membuat `inventory_batches`, `stock_adjustments`, `stock_adjustment_items`, `inventory_balances`, dan `inventory_movements` sebagai InnoDB.
- Migration berjalan forward-only sebagai batch 3 pada `retail_development`; seluruh 13 migration berstatus `Ran`, dan dry-run sesudahnya menghasilkan `Nothing to migrate`.
- Unique constraint kritis tersedia pada scope balance, ledger `source_key`, document/submission key, batch business key, dan nomor baris adjustment.
- Foreign key transaksi menggunakan `RESTRICT` untuk mencegah penghapusan referensi posted; actor nullable memakai `SET NULL` bila sesuai audit history.
- Kuantitas memakai `DECIMAL(18,3)`, unit cost/HPP `DECIMAL(18,6)`, dan nilai persediaan `DECIMAL(24,6)`; kolom proyeksi yang tidak boleh negatif memakai `UNSIGNED`.
- Sebelum dan sesudah migration development tetap memiliki 1 user, 1 cabang, 0 produk, dan 0 data inventory; tidak ada reset, rollback, drop, atau penghapusan data development.

**Pengujian dan quality gate:**

- Targeted domain/HTTP/schema/formatter: 20 test, 127 assertion — PASSED.
- Concurrency MariaDB/InnoDB: 1 test, 10 assertion — PASSED; dua proses PHP independen memperebutkan satu unit terakhir, tepat satu commit dan satu ditolak, satu movement keluar tercatat, saldo akhir nol.
- Full regression M0.1–M1.1: 101 test, 760 assertion, 0 failure — PASSED pada `retail_testing`.
- Laravel Pint `--dirty --format agent` — PASSED.
- `php artisan view:cache` — PASSED; 8 route persediaan terdaftar.
- `npm run build` — PASSED dengan Vite 8.3.5/Tailwind CSS 4; warning opsional `fontaine` tidak memengaruhi build.

**Acceptance criteria final:**

- [x] Posting saldo awal terjadi tepat sekali dan replay idempotency key yang sama tidak menggandakan stok.
- [x] Kegagalan parsial me-rollback balance, movement, audit, dan status dokumen secara atomik.
- [x] Moving Weighted Average, konversi satuan, dan nilai persediaan presisi tanpa float.
- [x] Transfer antar gudang dalam cabang menjaga total kuantitas/nilai.
- [x] Stok negatif ditolak, termasuk race dua proses pada stok terakhir.
- [x] Posted ledger immutable dan unique source mencegah movement ganda.
- [x] Rekonsiliasi bernilai nol untuk data valid dan mendeteksi projection drift.
- [x] Approval, larangan self-approval, RBAC, branch isolation, audit log, dan outbox teruji.
- [x] Migration development aman; schema MariaDB/InnoDB, DECIMAL, FK, dan unique constraint terverifikasi.
- [x] Regression suite, Pint, Blade compile, route audit, dan frontend build lulus.

**Batas scope:** Purchase Order, Goods Receipt, POS, Finance, dan milestone sesudah M1.1 tidak dimulai.

**Next gate:** STOP — M1.1 COMPLETED. Jangan memulai M1.2 sebelum instruksi eksplisit **LANJUT** dari pengguna.

## 19. M1.2 — Purchase Order — 23 Oktober 2026

**Status:** COMPLETED — seluruh acceptance criteria M1.2 lulus pada MariaDB/InnoDB.

**Fitur yang berfungsi:**

- Draft PO branch-scoped dengan nomor `PO/YYYY/MM/######` dari `DocumentNumberService`, supplier aktif, tanggal, estimasi kedatangan, catatan, dan item yang dapat ditambah/diubah/dihapus selama draft.
- Pemilihan barang cepat berdasarkan SKU/nama, satuan dasar atau satuan pembelian aktif, faktor konversi, harga beli, subtotal, total, serta validasi server-side Bahasa Indonesia.
- Snapshot supplier, alamat, SKU/nama barang, SKU supplier, satuan, faktor konversi, kuantitas, harga, dan subtotal menjaga histori dari perubahan master berikutnya.
- Lifecycle terkontrol: `draft` → `pending_approval` → `approved`/`rejected` → `sent`, serta pembatalan dari status yang diizinkan. Status `partially_received` dan `completed` disiapkan tetapi tidak ditransisikan sebelum M1.3.
- Approval memakai total PO, approval limit cabang/role/permission, larangan self-approval, row locking, audit append-only, dan transactional outbox.
- Create, update, submit, approve, reject, send, dan cancel memakai transaction/idempotency; replay key yang sama tidak menggandakan dokumen, approval, audit, atau outbox.
- Daftar, create, edit draft, detail, filter supplier/status/search, pagination, empty/error/loading state, workflow action, dan riwayat audit tersedia dalam UI Bahasa Indonesia.

**Batas bisnis yang terbukti:**

- Pembuatan, approval, pengiriman, penolakan, dan pembatalan PO tidak menulis `inventory_movements` atau `inventory_balances`.
- M1.2 tidak membuat tabel/record Goods Receipt, Purchase Invoice, AP entry, supplier payment, atau payment lain.
- `received_base_quantity` dimulai dari nol dan outstanding dihitung dari snapshot base quantity; hanya penerimaan sah M1.3 yang boleh mengubahnya.
- Approved PO tidak dapat diedit melalui Policy, Form Request, controller, maupun service; tidak ada route hard-delete PO.

**Migration dan schema:**

- `2026_10_23_025715_create_m1_2_purchase_order_tables` membuat `purchase_orders` dan `purchase_order_items` sebagai InnoDB.
- Dry-run menunjukkan hanya operasi create table, foreign key, unique constraint, dan index; migration berjalan forward-only sebagai batch 4 pada `retail_development`.
- Seluruh 14 migration development berstatus `Ran`. Setelah migration tetap 1 user, 1 cabang, 0 inventory movement, 0 inventory balance, 0 PO, dan 0 item PO.
- Dua tabel M1.2 memiliki 13 foreign key dan 7 unique index. Index query mencakup branch/status/tanggal, supplier/status/tanggal, document number scoped cabang, line number, dan product per PO.
- Total/subtotal memakai `DECIMAL(24,2)`, harga `DECIMAL(18,2)`, kuantitas/base/outstanding `DECIMAL(18,3)`, dan faktor konversi `DECIMAL(18,6)`, seluruhnya `UNSIGNED` bila tidak boleh negatif.
- `AccessControlSeeder` idempoten menambahkan 7 permission Purchase Order; tidak membuat akun atau transaksi bisnis.

**File/area utama:**

- Domain: `app/Domain/Purchasing/{Enums,Models,Services}/**` untuk state, model, BCMath calculator, draft transaction, dan workflow.
- Governance: penambahan reject generik ber-row-lock pada `ApprovalService`, policy PO, Gate registration, dan permission matrix.
- HTTP/UI: controller serta Form Request purchasing, route `/pembelian/purchase-order`, tiga Blade view PO, dan navigasi sidebar.
- Database/test: migration, factory PO/item, service/HTTP/schema/concurrency test M1.2.

**Pengujian dan quality gate:**

- Targeted domain/HTTP/schema plus concurrency final: 21 test, 136 assertion — PASSED.
- Concurrency MariaDB/InnoDB: 1 test, 11 assertion — PASSED; dua proses PHP independen menghasilkan nomor `PO/2026/10/000001` dan `PO/2026/10/000002`, dua dokumen unik, dan sequence akhir 2.
- Full regression M0.1–M1.2: 122 test, 952 assertion, 0 failure — PASSED pada `retail_testing`.
- Laravel Pint `--dirty --format agent` — PASSED; targeted suite diulang setelah formatting dan tetap lulus.
- `php artisan view:cache` — PASSED; 11 route purchasing terdaftar di bawah auth, current branch, dan permission middleware.
- `npm run build` — PASSED dengan Vite 8.3.5/Tailwind CSS 4; warning opsional `fontaine` tidak memengaruhi build.

**Temuan dan perbaikan:**

1. Fondasi approval hanya memiliki jalur approve; jalur reject generik ditambahkan dengan evaluator limit, row lock, actor, decision notes, dan audit yang konsisten.
2. Factory model modular semula mengandalkan inferensi namespace default; properti model eksplisit ditambahkan agar factory PO/item selalu mengarah ke domain model yang benar.
3. Workflow error semula berpotensi menjadi halaman 422 generik; controller mengembalikannya sebagai validation/error state pada halaman detail.
4. Policy approval diperketat agar self-approval tidak hanya ditolak di service, tetapi aksi juga tidak ditampilkan dan endpoint mengembalikan 403.
5. Product picker dibatasi 500 master aktif yang dimuat satu kali, dengan pencarian client-side dan maksimal 50 opsi per baris untuk menghindari request berulang dan DOM berlebihan. Untuk volume SKU jauh lebih besar, endpoint autocomplete terindeks perlu dievaluasi pada performance milestone.

**Keputusan terbuka:**

- DEC-007 tetap OPEN untuk nominal approval per role. Struktur dan enforcement tersedia; tanpa approval limit yang cocok, user non-Super Admin tidak dapat menyetujui PO.
- DEC-013 ditambahkan sebagai OPEN: apakah setiap item PO wajib memiliki mapping `supplier_products`. Default konfigurasi saat ini tidak mewajibkan mapping; bila diaktifkan, service menolak item tanpa relasi aktif.
- DEC-006 tetap OPEN; pajak dan diskon PO sengaja tidak diterapkan atau diasumsikan pada M1.2.

**Acceptance criteria final:**

- [x] Nomor PO unik dan transaction-safe; dua proses independen tidak menghasilkan duplikasi.
- [x] Draft multi-item dapat dibuat/diedit dengan supplier/produk/satuan aktif, qty positif, harga non-negatif, dan total presisi.
- [x] Snapshot historis lengkap tersimpan dan outstanding dasar disiapkan untuk penerimaan parsial M1.3.
- [x] State machine, approval/rejection, approval limit, duplicate prevention, self-approval, send, dan cancel teruji.
- [x] RBAC, Policy/Gate, branch isolation 404, Form Request, audit log, dan outbox teruji.
- [x] Transaction rollback dan idempotency tidak meninggalkan dokumen, sequence, claim, approval, audit, atau side effect parsial.
- [x] PO approved tidak dapat diedit dan dokumen tidak memiliki jalur hard-delete.
- [x] Tidak ada perubahan stok, inventory ledger/balance, AP, invoice, atau payment akibat PO.
- [x] Migration development aman; schema InnoDB, DECIMAL, FK, index, dan unique constraint terverifikasi.
- [x] Full regression, Pint, Blade compile, route audit, dan frontend build lulus.

**Risiko untuk M1.3:** aturan over-receipt/tolerance, penerimaan produk pengganti, lot/expiry saat receiving, dan siapa yang boleh menutup outstanding PO belum boleh diasumsikan. Goods Receipt harus memakai `InventoryPostingService`, unique source/idempotency, stable-order locking, dan tidak boleh membentuk AP.

**Next gate:** STOP — M1.2 COMPLETED. Jangan memulai M1.3 sebelum instruksi eksplisit **LANJUT** dari pengguna.

## 20. M1.3 — Goods Receipt / Penerimaan Barang — 23 Oktober 2026

**Status:** COMPLETED

**Tujuan:** menerima barang dari Purchase Order secara parsial atau penuh, menambah inventory tepat sekali melalui jalur posting M1.1, dan menjaga outstanding PO tanpa membentuk hutang atau pembayaran.

**Fitur yang berfungsi:**

- Draft Goods Receipt dibuat dari PO berstatus `approved`, `sent`, atau `partially_received`; snapshot supplier, produk, SKU, unit, faktor konversi, harga beli, dan konfigurasi batch disimpan.
- Draft dapat diedit atau dibatalkan tanpa mengubah inventory maupun outstanding PO. Receipt yang sudah posted immutable.
- Posting parsial dan penuh memperbarui `received_base_quantity` serta status PO menjadi `partially_received` atau `completed` secara konsisten.
- Incoming stock hanya melalui `InventoryPostingService`; movement, balance, MWA, batch, receipt, outstanding PO, audit, dan outbox commit/rollback dalam transaction yang sama.
- Batch/lot dan expiry divalidasi mengikuti konfigurasi produk. Identitas batch yang bentrok dan expiry yang sudah lewat pada tanggal penerimaan ditolak.
- Over-receipt ditolak selama DEC-014 masih OPEN. Produk pengganti, invoice, AP, pembayaran, retur supplier, dan POS tidak dibuat.
- Halaman daftar, tambah/edit draft, dan detail Penerimaan Barang tersedia dalam Bahasa Indonesia dengan pencarian, filter status, pagination, empty/error/loading state, serta aksi sesuai permission.

**Transaction dan keamanan:**

- Posting memakai idempotency key, unique source constraint, bounded deadlock retry, serta row locking berurutan pada receipt, gudang, PO, item receipt, item PO, batch, dan inventory balance.
- Branch dan warehouse harus konsisten dengan PO serta akses pengguna; policy, permission, dan scoped query mencegah akses lintas cabang.
- Dua proses yang memperebutkan outstanding terakhir menghasilkan tepat satu posting berhasil; proses lain ditolak tanpa menambah stok atau received quantity kedua kali.
- Nomor Goods Receipt dialokasikan melalui document sequence dan terbukti unik pada dua proses independen.
- Audit log dan transactional outbox mencatat pembuatan, perubahan, pembatalan, dan posting kritis.

**File/area utama yang dibuat atau diubah:**

- Migration `database/migrations/2026_10_23_101601_create_m1_3_goods_receipt_tables.php`.
- Domain `app/Domain/Purchasing/{Enums,Models,Services}/GoodsReceipt*` dan relasi Goods Receipt pada model Purchase Order.
- Perluasan terkontrol `app/Domain/Inventory/Services/InventoryPostingService.php` untuk incoming receipt.
- Policy, Form Request, controller, routes, permission configuration, factory, sidebar, dan Blade views Goods Receipt.
- Test service, HTTP/controller, schema, dan concurrency Goods Receipt di `tests/Feature/**`.

**Database dan schema:**

- Tabel baru: `goods_receipts` dan `goods_receipt_items`, engine InnoDB, kolom qty/uang/cost `DECIMAL`, foreign key dengan kebijakan delete eksplisit, unique constraint untuk public ID, creation/posting idempotency key, nomor per cabang, delivery note supplier, dan identitas baris.
- Schema testing memverifikasi 2 tabel InnoDB, 8 unique constraint bernama, 9 foreign key transaksi `RESTRICT`, serta presisi kolom numerik kritis.
- `php artisan migrate --pretend --no-interaction` ditinjau dan hanya berisi create table, foreign key, unique constraint, serta index; tidak ada operasi drop.
- Setelah audit dry-run dan persetujuan eksplisit pengguna, migration dijalankan forward-only sebagai batch 5 pada `retail_development`; seluruh 15 migration berstatus `Ran`.
- Audit aktual development memverifikasi `goods_receipts` dan `goods_receipt_items` memakai InnoDB dengan 12 foreign key dan 8 unique constraint. Kedua tabel baru masih kosong.
- Sebelum dan sesudah deployment tetap terdapat 1 user/Super Admin, 1 cabang, 1 gudang, 0 PO/item PO, serta 0 batch/movement/balance inventory. Tidak ada database proyek lain yang diubah.

**Hasil test dan quality gate:**

- Targeted M1.3 final: 12 test, 117 assertion, 0 failure — PASSED pada MariaDB `retail_testing`.
- Concurrency dua proses independen: nomor GR menghasilkan `GR/2026/10/000001` dan `GR/2026/10/000002` tanpa duplikasi; concurrent posting outstanding terakhir menghasilkan satu sukses dan satu penolakan, saldo 5, satu movement, serta PO `completed` — PASSED.
- Full regression suite M0.1–M1.3: 134 test, 1.109 assertion, 0 failure — PASSED pada MariaDB `retail_testing`.
- Laravel Pint — PASSED setelah perapihan final.
- Blade compile/cache — PASSED.
- Route audit — PASSED; 8 route Goods Receipt dan 11 route Purchase Order terdaftar sesuai middleware aplikasi.
- Smoke authorization/UI setelah deployment — PASSED; 4 test, 20 assertion untuk redirect guest, penolakan permission, render halaman, draft HTTP, validation, dan branch isolation.
- Vite 8.3.5/Tailwind CSS 4 production build — PASSED; warning opsional `fontaine` tidak memengaruhi output.

**Temuan dan perbaikan:**

1. Proteksi immutability model disesuaikan memakai raw original status agar update internal yang sah sebelum posting tidak salah ditolak.
2. Fixture MWA batch diselaraskan dengan scope batch yang sama agar menguji costing aktual, bukan setup data yang berbeda.
3. Concurrency test menjaga `retail_testing` dengan guard nama database dan cleanup terisolasi; development tidak pernah di-reset.

**Acceptance criteria:**

- [x] Draft penerimaan dari PO berfungsi tanpa efek stok.
- [x] Penerimaan parsial dan penuh memperbarui outstanding/status PO dengan benar.
- [x] Posting inventory atomik melalui `InventoryPostingService` dan MWA terjaga.
- [x] Retry idempotent, rollback, double-post protection, source uniqueness, dan over-receipt rejection teruji.
- [x] Batch, lot, expiry, branch isolation, warehouse scope, RBAC, audit, serta immutable posted receipt teruji.
- [x] Dua proses independen pada MariaDB/InnoDB tidak menggandakan nomor, stok, movement, atau outstanding.
- [x] Targeted suite, full regression, Pint, Blade compile, schema audit, route audit, dan frontend build lulus.
- [x] Tidak ada Purchase Invoice, AP entry, pembayaran, retur supplier, atau POS yang dibuat.

**Keputusan terbuka:** DEC-014 tetap OPEN. Default aman M1.3 menolak kuantitas di atas outstanding; tolerance/approval hanya dapat ditambahkan setelah kebijakan procurement disahkan.

**Next gate:** STOP — M1.3 COMPLETED. Jangan memulai M1.4 atau milestone lain sebelum instruksi eksplisit **LANJUT** dari pengguna.
