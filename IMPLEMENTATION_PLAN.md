# Implementation Plan — Retail Management System

**Versi rencana:** 1.0  
**Tanggal:** 23 Oktober 2026
**Status:** M0.1, M0.2, M0.3, M1.1, dan M1.2 COMPLETED pada MariaDB/InnoDB; M1.3 Goods Receipt **IN_PROGRESS**
**Sumber kebenaran:** `ai/retail management/PRD_Retail_Management_System.md`, `ai/retail management/architecture.md`, dan `ai/retail management/design.md`

## 1. Tujuan dan batas rencana

Rencana ini menguraikan pembangunan Retail Management System sebagai **modular monolith Laravel**. Urutan kerja dirancang untuk mencegah stok, hutang, pembayaran, dan penjualan menjadi tidak dapat direkonsiliasi.

Ketentuan eksekusi:

1. Hanya satu milestone aktif pada satu waktu.
2. Sebelum milestone dimulai, jelaskan tujuan, file, tabel, migrasi, dan pengujian yang akan terlibat.
3. Setelah implementasi, jalankan pengujian yang relevan, laporkan bukti hasil, perbarui `PROJECT_PROGRESS.md`, lalu berhenti.
4. Milestone berikutnya hanya boleh dimulai setelah instruksi eksplisit **LANJUT**.
5. Status `Selesai` hanya diberikan setelah seluruh acceptance criteria dan automated test milestone lulus.
6. Dokumen terposting tidak diedit atau dihapus permanen; koreksi menggunakan reversal, return, credit note, atau adjustment.
7. Tidak ada mutasi stok dari controller, Livewire component, atau form master. Semua mutasi melalui action/service posting dan stock ledger.
8. Bahasa UI adalah Bahasa Indonesia, format mata uang IDR, waktu disimpan UTC dan ditampilkan `Asia/Jakarta`.

## 2. Ringkasan hasil analisis proyek

### 2.1 Kondisi awal sebelum implementasi M0.1 (historis)

Tabel berikut merekam baseline saat rencana pertama disusun. Kondisi aktual setelah implementasi dan audit M0.1 dicatat di `PROJECT_PROGRESS.md`.

| Area | Kondisi | Dampak |
|---|---|---|
| Framework | Laravel 13.38.0, PHP 8.5.6 | API framework harus diverifikasi terhadap Laravel 13 sebelum implementasi. |
| Dependency manager | Composer 2.9.8; metadata valid; audit tidak menemukan advisory | Baseline backend sehat. |
| Frontend runtime | Node 24.15.0, npm 11.12.1 | Runtime tersedia, tetapi dependency frontend belum terpasang. |
| Backend dependency | Hanya skeleton Laravel dan development tools; Laravel Boost 2.10.3 telah dipasang | Livewire, auth scaffolding, RBAC, PDF, dan Excel belum tersedia. Penambahan dependency memerlukan persetujuan pada milestone terkait. |
| Frontend dependency | Tailwind CSS 4 dan Vite 8 tercantum pada `package.json` | `node_modules` dan lockfile npm belum ada; build belum dapat dianggap tervalidasi. |
| Database | SQLite lokal; tiga migrasi bawaan telah dijalankan | Belum sesuai pilihan produksi PostgreSQL-preferred/MySQL 8+. SQLite tetap cocok untuk sebagian tes cepat, tetapi tes locking/concurrency wajib memakai DB target. |
| Kode aplikasi | Skeleton: `User`, welcome route/view, provider kosong | Belum ada domain/module, auth UI, RBAC, transaksi, atau reusable UI. |
| Test | Dua tes contoh lulus (2 test, 2 assertion) | Hanya membuktikan skeleton dapat boot; belum menguji kebutuhan retail. |
| Locale/timezone | `en` dan `UTC` | Harus diubah menjadi label/locale Indonesia dan display timezone Asia/Jakarta tanpa mengubah prinsip penyimpanan UTC. |
| Version control | Direktori bukan repository Git | Risiko audit perubahan dan rollback pengembangan; inisialisasi Git direkomendasikan sebelum implementasi, hanya setelah persetujuan pengguna. |
| Project guidance | Laravel Boost, `AGENTS.md`, dan skill lokal tersedia; `.ai/rules` tidak ada | Konvensi khusus belum dapat diinferensikan karena aplikasi masih skeleton. Ikuti architecture/design dan Laravel conventions. |

### 2.2 Kebutuhan lintas modul yang wajib menjadi fondasi

- Scope cabang/gudang pada data dan authorization meskipun deployment awal satu cabang.
- RBAC per modul, aksi, cabang, dan batas nominal approval.
- Nomor dokumen transaction-safe melalui `document_sequences`, bukan `MAX(id)+1`.
- Idempotency key dan unique constraint untuk semua operasi posting penting.
- Database transaction, stable-order row locking, dan bounded deadlock retry untuk stok/uang.
- `inventory_movements` sebagai ledger immutable; `inventory_balances` hanya proyeksi performa yang diperbarui atomik.
- Historical snapshot untuk harga, pajak, diskon, HPP, nama/SKU, dan atribusi supplier.
- Audit log append-only untuk tindakan kritis, dengan masking PII/secret.
- Internal status/enum stabil dalam Bahasa Inggris; seluruh label dan pesan pengguna dalam Bahasa Indonesia.
- Query laporan read-only, terindeks, dipaginasi, dan dapat direkonsiliasi ke ledger.

## 3. Ketidaksesuaian dan resolusi antar dokumen

Tabel berikut menetapkan istilah kanonis untuk implementasi. Resolusi tidak mengurangi scope PRD.

| Topik | PRD | Architecture/Design | Resolusi implementasi |
|---|---|---|---|
| Ledger stok | `stock_movements`, `stock_balances` | `inventory_movements`, `inventory_balances` | Gunakan nama architecture: `inventory_movements` dan `inventory_balances`; catat sebagai padanan requirement PRD. |
| Rak/lokasi | `racks` | `shelf_locations`, `product_locations` | Gunakan `shelf_locations` untuk hierarki fisik dan `product_locations` untuk penempatan produk; label UI tetap “Rak & Lokasi”. |
| Member | `members` | `customers`, `membership_tiers` | Gunakan `customers`; atribut membership berada pada customer/tier. “Member” adalah label UI/kondisi customer, bukan entitas duplikat. |
| Barang-supplier | `product_suppliers` | `supplier_products` | Gunakan `supplier_products` secara konsisten. |
| Harga | `price_tiers`, `product_prices` | `price_lists`, `product_prices`, `membership_tiers` | `price_lists` mendefinisikan strata harga; `membership_tiers` mendefinisikan level member; `product_prices` menyimpan harga efektif. |
| Shift kasir | `cashier_shifts` | `cash_shifts` | Gunakan `cash_shifts`; label UI “Shift Kasir”. |
| Poin | `member_point_ledger` | `loyalty_ledger` | Gunakan `loyalty_ledger`; saldo poin selalu turunan ledger. |
| Bundle | `bundles`, `bundle_components`, `bundle_movements` | `bundle_recipes`, `bundle_components`, `bundle_operations` | Gunakan model recipe/version dan operation dari architecture agar BOM terposting dapat dibekukan. |
| Hutang | Invoice dan payment allocation | Architecture menambahkan `ap_entries` dan credit note | Gunakan AP ledger (`ap_entries`) agar saldo dapat direkonsiliasi; invoice/payment tetap dokumen sumber. |
| Fase | PRD memakai 3 fase | Architecture memakai F0–F4 | Gunakan F0–F4 karena memisahkan fondasi dan hardening; seluruh scope PRD tetap dipetakan. |
| Target cabang | PRD meminta keputusan | Architecture menetapkan satu cabang awal, multi-cabang-ready | Jadikan satu cabang sebagai rollout awal, tetapi semua transaksi dan policy tetap branch-scoped. |
| HPP | FIFO atau moving average belum dipilih | Moving weighted average direkomendasikan | Rekomendasi baseline: moving weighted average. Keputusan harus disahkan sebelum milestone ledger stok. |
| Database | PostgreSQL atau MySQL belum final | MariaDB dengan InnoDB | **Resolusi baru:** MariaDB menjadi database utama development dan production; SQLite/PostgreSQL tetap dipertahankan dan tidak dihapus. |
| Status UI | UI wajib Bahasa Indonesia | Beberapa contoh status masih berbahasa Inggris | Nilai enum tetap Inggris; presenter/translation wajib menghasilkan label Indonesia. |
| EOD | PRD menaruh EOD di MVP | Architecture menyebut akurasi EOD di F1 dan EOD penuh di F2 | F1 menyediakan open/close shift dan rekonsiliasi cash minimum; approval/toleransi dan settlement kompleks disempurnakan pada F2. |
| Penerimaan dan faktur | Faktur dapat hadir saat receiving | Architecture memisahkan receipt dan invoice | Posting receipt mengubah stok satu kali; posting invoice mengubah AP/cash, tidak menambah stok lagi. Relasi receipt–invoice menyimpan traceability. |
| Uang | PRD mengizinkan DECIMAL atau integer rupiah | Architecture memilih `DECIMAL(18,2)` | Gunakan `DECIMAL(18,2)` dan string/value object Money pada PHP; dilarang memakai float. Pembulatan IDR ditetapkan eksplisit. |

## 4. Keputusan yang harus dikunci sebelum coding transaksi

| ID | Keputusan | Rekomendasi aman | Batas keputusan |
|---|---|---|---|
| DEC-001 | Database utama development dan production | **APPROVED:** MariaDB dengan InnoDB; database development dan testing wajib terpisah | Diubah dan disahkan 22 Oktober 2026 |
| DEC-002 | HPP | **DISETUJUI:** Moving Weighted Average; snapshot HPP pada sale; larang backdate ke periode terkunci | Disahkan 22 Oktober 2026 |
| DEC-003 | Stok negatif | **DISETUJUI:** dilarang untuk transaksi operasional normal | Disahkan 22 Oktober 2026 |
| DEC-004 | Scope operasional | **DISETUJUI:** satu cabang awal, schema multi-cabang-ready; jumlah gudang/register ditetapkan di M0.2 | Disahkan sebagian 22 Oktober 2026 |
| DEC-005 | Auth/RBAC | **DISETUJUI sebagian:** session auth; keputusan paket RBAC dan MFA privileged dilanjutkan pada milestone governance/hardening | Session auth disahkan 22 Oktober 2026 |
| DEC-006 | Pajak/pembulatan | **OPEN:** struktur tax rule effective-dated dan pembulatan configurable telah dibuat tanpa tarif hardcoded; kebijakan per-line/per-total tetap harus disahkan akuntan | Sebelum implementasi perhitungan pajak final di M1.3 |
| DEC-007 | Approval | **OPEN untuk nominal bisnis:** struktur limit per cabang/role/permission aktif; PO memakai total dokumen dan menolak approval bila limit yang sesuai belum dikonfigurasi | Sebelum penggunaan operasional tiap tipe approval |
| DEC-008 | Offline POS | **DISETUJUI:** MVP online-required; koneksi putus mencegah posting dan tidak mengaku sukses | Disahkan 22 Oktober 2026 |
| DEC-009 | Cetak MVP | Browser print untuk 58/80 mm; print bridge ditunda sampai perangkat tervalidasi | Sebelum M1.5 |
| DEC-010 | Noncash | F1 cash-only; provider QRIS/EDC dan callback dipilih sebelum M2.7 | Sebelum M2.7 |
| DEC-011 | Konsinyasi | Settlement sold-through atau jadwal kontrak | Sebelum M2.6 |
| DEC-012 | Perangkat/volume | SKU, transaksi/hari, kasir simultan, scanner/printer/OS | Sebelum performance/device acceptance |
| DEC-013 | Kewajiban relasi barang-supplier pada PO | **OPEN:** default aman tidak mewajibkan mapping; bila konfigurasi diaktifkan, item tanpa relasi supplier aktif ditolak | Sebelum penerapan kebijakan procurement ketat |
| DEC-014 | Toleransi over-receipt | **OPEN:** M1.3 menolak penerimaan di atas outstanding; toleransi/approval baru boleh ditambahkan setelah kebijakan procurement disahkan | Sebelum over-receipt diizinkan |

Keputusan yang belum disahkan dicatat sebagai `OPEN`, bukan diasumsikan selesai. Rekomendasi boleh dipakai untuk desain awal hanya bila pengguna menyetujuinya pada pembukaan milestone.

## 5. Arsitektur implementasi

### 5.1 Batas modul

```text
app/Domain/
  Shared/                 Money, DTO, exception, idempotency, document number
  Organization/           branch, warehouse, register, user branch scope
  Access/                 roles, permissions, approvals, audit
  Catalog/                product, barcode, unit, category, brand, location
  Suppliers/              supplier dan supplier-products
  Pricing/                price list, tax rule, pricing engine
  Purchasing/             PO, receipt, invoice
  Inventory/              movement, balance, batch, count, adjustment, bundle
  AccountsPayable/        AP ledger, payment, allocation, credit note
  Sales/                  shift, sale, payment, return, void
  Loyalty/                customer/member, point ledger, voucher
  Promotions/             campaign dan rule
  Reports/                query dan export read-only
```

Dependency direction:

1. `Shared` tidak bergantung pada domain bisnis lain.
2. Modul berkomunikasi melalui action contract, domain event setelah commit, dan identifier; tidak mengakses tabel modul lain secara liar dari UI.
3. Controller/FormRequest/Livewire hanya mengatur input, authorization, dan presentation.
4. Action/use case mengendalikan transaction boundary.
5. Service menghitung pricing, costing, allocation, atau reconciliation secara deterministik.
6. Query report tidak boleh mengubah state.
7. External payment/print/storage berada di balik contract bila integrasi benar-benar diperlukan.

### 5.2 Pola transaksi kritis

Urutan standar posting:

1. Autentikasi, policy, branch scope, dan validation.
2. Klaim idempotency key dengan unique constraint.
3. Buka database transaction.
4. Lock source document dan balance terkait dalam urutan ID stabil.
5. Validasi ulang status, versi, qty, harga, outstanding, dan payment state.
6. Tulis dokumen/ledger dan update projection balance.
7. Tulis audit log serta transactional outbox.
8. Tandai dokumen posted.
9. Commit.
10. Setelah commit, proses print/export/notifikasi; kegagalan side effect tidak boleh memposting ulang transaksi.

## 6. Urutan implementasi aman untuk stok, keuangan, dan POS

```text
Governance + RBAC + branch scope + document sequence + idempotency
  -> Master katalog/supplier/unit/pajak/harga
  -> Stock ledger + balance projection + opening balance + reconciliation
  -> PO (belum mengubah stok/keuangan)
  -> Goods Receipt (menambah stok tepat sekali)
  -> Purchase Invoice (membentuk cash/AP tanpa menambah stok lagi)
  -> AP ledger + payment allocation
  -> Register + open shift
  -> POS quote authoritative
  -> POS cash posting atomik: sale + stock + HPP snapshot + cash
  -> Receipt/reprint dan basic sales/margin report
  -> Close shift/EOD reconciliation
  -> Return/void/reversal
  -> Opname/write-off/bundle/consignment
  -> Noncash/payment gateway
  -> Promo/member/loyalty
```

Alasan urutan:

- Sale tidak boleh dibuat sebelum ledger dan costing terbukti benar.
- Invoice dipisahkan dari receipt agar stok tidak bertambah dua kali.
- AP payment baru aman setelah invoice/outstanding memiliki ledger yang dapat dikunci.
- Return/void dibangun setelah transaksi asal stabil, karena keduanya adalah reversal terhadap snapshot asli.
- Promo, poin, voucher, QRIS, dan split payment menambah state machine serta risiko; semuanya ditunda sampai POS cash dan rekonsiliasi lulus.

## 7. Roadmap fase dan milestone

### F0 — Fondasi dan governance

#### M0.1 — Baseline teknis, struktur modular, auth, dan design system

**Status:** COMPLETED — diaudit 22 Oktober 2026. Bukti implementasi, perbaikan, dan pengujian tercatat di `PROJECT_PROGRESS.md`.

**Tujuan:** menetapkan platform yang dapat dipakai semua modul tanpa membuat transaksi bisnis.

**File/area:** `composer.json`, `package.json`, `config/app.php`, `bootstrap/app.php`, `app/Domain/Shared/**`, `app/Http/**`, `app/Livewire/**`, `resources/css/app.css`, `resources/views/components/**`, `routes/web.php`, `tests/Feature/Auth/**`.

**Tabel:** `users` (penyesuaian aman), `password_reset_tokens`, `sessions`; belum ada tabel transaksi.

**Tugas:**

- Sahkan DB target, strategi auth, dependency Livewire/auth, dan lockfile frontend.
- Bentuk folder modular sesuai architecture tanpa membuat abstraksi spekulatif.
- Terapkan login/logout, rate limit, session security, dan dasar MFA akun istimewa sesuai keputusan.
- Atur UI Bahasa Indonesia, formatter IDR, display timezone Asia/Jakarta, dan internal UTC.
- Implementasikan AppShell dan komponen dasar dari `design.md` dengan Tailwind v4.
- Tambahkan health/smoke test, formatter, dan pipeline test/build minimum.

**Dependensi:** DEC-001 dan DEC-005; izin menambah dependency.

**Acceptance criteria:**

- Pengguna dapat login/logout melalui UI Indonesia; endpoint terlindungi menolak guest.
- Layout responsif dasar, focus state, error state, dan token desain sesuai `design.md`.
- Format IDR dan tanggal WIB diuji tanpa mengubah nilai raw.
- Test auth/authorization dasar lulus; `composer validate`, `composer audit`, test, dan frontend build lulus.
- Tidak ada fitur transaksi/stok yang dibuat.

#### M0.2 — Organisasi, RBAC, approval, audit, idempotency, dan nomor dokumen

**Status implementasi 22 Oktober 2026:** **COMPLETED**. Delapan migration berjalan secara forward-only pada `retail_development`; full suite 59 test/325 assertion lulus pada `retail_testing`; seluruh 24 tabel pada masing-masing database memakai InnoDB; dan test dua proses independen untuk nomor dokumen serta idempotency lulus tanpa duplikasi atau side effect ganda.

**Tujuan:** menyediakan kontrol akses dan primitif reliabilitas sebelum master/transaksi.

**File/area:** `app/Domain/Organization/**`, `app/Domain/Access/**`, `app/Domain/Shared/{Idempotency,DocumentNumber,Outbox}/**`, policies, middleware, requests, seeders, factories, tests.

**Tabel:** `branches`, `warehouses`, `registers`, `roles`, `permissions`, pivot user-role/permission, `user_branches`, `approval_requests`, `approval_limits`, `audit_logs`, `idempotency_keys`, `document_sequences`, `outbox_events`.

**Tugas:** branch scope, permission matrix, approval limit, append-only audit, sequence row locking, reusable idempotency service, initial role seeding.

**Dependensi:** M0.1; DEC-004 dan DEC-007.

**Acceptance criteria:**

- Akses lintas cabang ditolak tanpa membocorkan keberadaan record.
- Matriks permission diuji di policy dan minimal satu HTTP wiring test per area.
- Dua request nomor dokumen konkuren tidak menghasilkan nomor duplikat.
- Retry idempotency key mengembalikan hasil sama; payload berbeda dengan key sama ditolak.
- Audit critical action menyimpan actor, branch, reason, dan snapshot termasking.

#### M0.3 — Master pendukung, katalog, supplier, harga dasar, dan pajak

**Status implementasi 22 Oktober 2026:** **COMPLETED**. Empat migration forward-only berjalan pada `retail_development`; suite final 82 test/578 assertion lulus pada `retail_testing`; 16 tabel M0.3 terverifikasi InnoDB; CRUD/inaktivasi, hierarki, unit dan konversi, supplier, lokasi branch-scoped, harga efektif, pajak configurable, RBAC, audit, foreign key, serta unique constraint telah diuji. DEC-006 tetap OPEN untuk kebijakan perhitungan pajak final.

**Tujuan:** membangun master yang dibutuhkan purchasing/inventory tanpa mengizinkan edit stok langsung.

**File/area:** `app/Domain/{Catalog,Suppliers,Pricing}/**`, `app/Http/{Controllers,Requests}/Master/**`, policies, Blade views/components, factories, migrations, routes, permission config, dan tests.

**Tabel:** `departments`, `categories`, `subcategories`, `brands`, `units`, `products`, `product_barcodes`, `product_units`, `shelf_locations`, `product_locations`, `suppliers`, `supplier_products`, `price_lists`, `product_prices`, `price_change_logs`, `tax_rules`.

**Tugas:** CRUD terotorisasi, unique database constraints, effective-dated price/tax, soft inactivation, search/pagination, import bukan scope awal.

**Dependensi:** M0.2; DEC-006.

**Acceptance criteria:**

- SKU dan barcode unik ditolak di database dan ditampilkan sebagai error Indonesia.
- Produk yang sudah direferensikan tidak dapat hard-delete.
- Tidak ada field/action untuk mengubah on-hand stock pada master produk.
- Harga/pajak memiliki periode efektif dan riwayat perubahan.
- Daftar master memiliki loading/empty/error/permission/pagination states.

### F1 — Alur MVP terintegrasi

#### M1.1 — Stock ledger, balance projection, costing, dan opening balance

**Tujuan:** membuat satu-satunya jalur mutasi stok sebelum purchasing/POS.

**File/area:** `app/Domain/Inventory/{Models,Actions,Services,Enums}/**`, reconciliation command/query, factories, integration/concurrency tests.

**Tabel:** `inventory_batches`, `inventory_movements`, `inventory_balances`, `stock_adjustments`, `stock_adjustment_items` (opening balance memakai adjustment bertipe khusus).

**Tugas:** immutable movement, balance locking, moving average/FIFO sesuai keputusan, stock status, opening balance approval, reconciliation.

**Dependensi:** M0.3; DEC-002 dan DEC-003.

**Acceptance criteria:**

- Setiap perubahan qty memiliki movement source unik dan audit trail.
- Movement dan balance commit/rollback bersama.
- Retry source/idempotency tidak menggandakan stok.
- Dua pengurangan stok terakhir secara konkuren tidak menghasilkan oversell/negative stock.
- Rekonsiliasi ledger dan balance menghasilkan nol selisih untuk data valid.
- HPP tidak menggunakan float dan mengikuti metode yang disahkan.

**Status implementasi 23 Oktober 2026:** **COMPLETED**. Lima tabel M1.1 berjalan forward-only pada `retail_development`; stock mutation hanya melalui service atomik dengan row lock, source unik, idempotency, audit, dan outbox. Moving Weighted Average memakai aritmetika BCMath/DECIMAL, stok negatif ditolak, ledger posted immutable, serta rekonsiliasi tersedia. Targeted suite lulus 20 test/127 assertion, concurrency dua proses lulus 1 test/10 assertion, dan full regression suite lulus 101 test/760 assertion pada `retail_testing`. Pint, Blade compile, delapan route persediaan, dan frontend build lulus.

#### M1.2 — Purchase Order

**Tujuan:** menyediakan lifecycle PO tanpa efek stok atau keuangan.

**Tabel:** `purchase_orders`, `purchase_order_items`.

**File/area:** `app/Domain/Purchasing/**`, controller/Form Request purchasing, policy, Blade views responsif, routes, factories, dan tests.

**Dependensi:** M0.2 dan M0.3.

**Acceptance criteria:** nomor PO unik/transaction-safe; state machine dan approval valid; snapshot supplier/harga tersimpan; PO draft dapat diedit, approved tidak diedit langsung; PO tidak membuat movement atau AP entry.

**Status implementasi 23 Oktober 2026:** **COMPLETED**. Migration dua tabel PO berjalan forward-only sebagai batch 4 pada `retail_development`; kedua tabel InnoDB dengan 13 foreign key dan 7 unique index. Draft multi-item, snapshot supplier/produk/satuan/konversi/harga, perhitungan BCMath, lifecycle draft → pending approval → approved/rejected → sent/cancelled, approval limit, larangan self-approval, RBAC, branch isolation, audit/outbox, idempotency, dan preparation field outstanding telah diterapkan tanpa efek inventory atau AP. Targeted M1.2 lulus 21 test/136 assertion termasuk nomor PO dua proses independen; full regression suite lulus 122 test/952 assertion pada `retail_testing`. Pint, Blade compile, 11 route purchasing, dan Vite/Tailwind build lulus. DEC-007 dan DEC-013 tetap OPEN pada bagian kebijakan bisnisnya.

#### M1.3 — Goods receipt parsial dari PO

**Status implementasi 23 Oktober 2026:** **IN_PROGRESS** setelah instruksi eksplisit **LANJUT M1.3**. Dependency M1.1 dan M1.2 telah diverifikasi. Rencana perubahan dibatasi pada dua tabel Goods Receipt, domain service posting melalui `InventoryPostingService`, policy/Form Request/controller, UI receiving, factory, serta integration/schema/concurrency test. Tidak ada Purchase Invoice, AP, pembayaran, retur supplier, atau POS yang dibuat.

**Tujuan:** menerima barang tepat sekali dan menjaga outstanding PO.

**Tabel:** `goods_receipts`, `goods_receipt_items`, `inventory_movements`, `inventory_balances`; relasi ke PO/item.

**File/area:** action `PostGoodsReceipt`, receiving UI, policies/requests, integration dan concurrency tests.

**Dependensi:** M1.1 dan M1.2 terpenuhi; DEC-014 tetap OPEN sehingga default aman menolak over-receipt.

**Acceptance criteria:** supplier/item termuat dari PO; partial receipt menyisakan outstanding; over-receipt ditolak/di-approval; double post tidak menambah stok dua kali; failure roll back receipt, movements, balances, dan status PO bersama.

#### M1.4 — Purchase invoice tunai/kredit dan AP minimum

**Tujuan:** mencatat invoice dan kewajiban tanpa menduplikasi stok receipt.

**Tabel:** `purchase_invoices`, `purchase_invoice_items`, receipt-invoice allocations bila diperlukan, `ap_entries`, payment record minimum untuk invoice tunai.

**File/area:** `app/Domain/{Purchasing,AccountsPayable}/**`, Finance UI minimum, tests.

**Dependensi:** M1.3; DEC-006.

**Acceptance criteria:** nomor invoice normalized unik per supplier; invoice receipt tidak membuat movement baru; cash dan credit menghasilkan entry berbeda dan benar; due date/outstanding dapat direkonsiliasi; rollback atomik pada error.

#### M1.5 — Shift kasir dan POS cash atomik

**Tujuan:** menjual stok dengan pembayaran tunai secara aman dan keyboard-first.

**Tabel:** `registers`, `cash_shifts`, `sales`, `sale_items`, `sale_payments`, `cash_movements`, `inventory_movements`, `inventory_balances`.

**File/area:** `app/Domain/Sales/**`, Pricing quote service minimum, `app/Livewire/Pos/**`, receipt views/CSS, policies, integration/concurrency/feature tests.

**Dependensi:** M1.1, M0.3, dan register dari M0.2; DEC-008/009.

**Acceptance criteria:** shift open wajib; scan barcode menambah item tepat sekali; checkout menghitung ulang harga server-side; sale, cash payment, movement, balance, dan HPP snapshot commit atomik; retry identik menghasilkan sale yang sama; stok tidak cukup ditolak; dua kasir tidak dapat menjual stok terakhir yang sama; print failure tidak membuat checkout ulang.

#### M1.6 — Receipt/reprint, laporan dasar, dan EOD minimum

**Tujuan:** menutup loop operasional MVP dan membuktikan rekonsiliasi.

**Tabel:** tabel F1 read-only plus metadata reprint/audit bila diperlukan; tidak membuat ledger duplikat.

**File/area:** receipt 58/80 mm, Sales/Purchase/Stock report queries, shift close action, `app/Domain/Reports/**`, tests.

**Dependensi:** M1.4 dan M1.5.

**Acceptance criteria:** reprint berlabel COPY dan tidak mengubah stok/sale; laporan penjualan memakai HPP snapshot; stock report cocok dengan ledger; close shift menghitung expected cash dari ledger, menyimpan counted cash dan variance, serta mengunci shift; seluruh alur PO → receipt → invoice → sale → EOD lulus integration test.

### F2 — Operasi lanjutan dan reversal

| Milestone | Scope | Tabel utama | Dependensi | Acceptance gate ringkas |
|---|---|---|---|---|
| M2.1 | Pembayaran hutang parsial/penuh | `supplier_payments`, `supplier_payment_allocations`, `ap_entries` | M1.4 | Allocation tidak melebihi payment/outstanding; dua pembayaran konkuren tidak membuat outstanding negatif; bukti bayar terotorisasi. |
| M2.2 | Retur supplier dan credit note | `supplier_returns`, items, `supplier_credit_notes`, inventory/AP ledger | M1.3, M2.1 | Qty retur tidak melebihi eligible; stok dan AP dibalik atomik; retry aman. |
| M2.3 | Retur penjualan, refund, dan void | `sale_returns`, items, `sale_voids`, refund/payment records | M1.5 | Referensi sale wajib; cumulative return dibatasi; sellable/quarantine benar; HPP asli dan uang dibalik; nota asli immutable. |
| M2.4 | Write-off dan stock opname | `stock_counts`, lines, recounts, adjustments | M1.1 | Snapshot/cutoff jelas; approval wajib; adjustment satu kali; variance qty/nilai dapat direkonsiliasi. |
| M2.5 | Bundle/banded dan pecah banded | recipe/version, component, operation tables | M1.1 | Komponen keluar dan paket masuk atomik; BOM version tersimpan; cost conservation diuji. |
| M2.6 | Konsinyasi | agreement, consignment movements/settlements | M1.1, M1.5 | Ownership terpisah; sale mengurangi stok konsinyasi; kewajiban mengikuti kontrak, bukan invoice biasa. |
| M2.7 | Debit/QRIS/split payment | payment attempts/events/reconciliation | M1.5, provider decision | Pending tidak dianggap lunas; webhook signature dan deduplication diuji; split hanya posted saat seluruh komponen valid. |
| M2.8 | EOD penuh, price card, barcode label | shift approvals, print templates | M2.3, M2.7 | Variance approval, late settlement handling, barcode fisik dapat discan, print retry aman. |

Setiap milestone F2 tetap mengikuti alur milestone lengkap dan gate **LANJUT**; baris tabel bukan izin mengerjakan secara batch.

### F3 — Customer, pricing lanjutan, promosi, dan loyalitas

| Milestone | Scope | Tabel utama | Dependensi | Acceptance gate ringkas |
|---|---|---|---|---|
| M3.1 | Customer/member dan strata harga | `customers`, `membership_tiers`, price tables | M0.3, M1.5 | Consent tercatat; harga eligible transparan; snapshot harga tidak berubah saat master berubah. |
| M3.2 | Promotion engine dan voucher | promotions/conditions/items/redemptions/vouchers | M3.1 | Boundary waktu WIB benar; prioritas/stacking deterministik; expired promo kembali ke harga normal tanpa overwrite. |
| M3.3 | Loyalty ledger | `loyalty_rules`, `loyalty_ledger` | M3.1, M2.3 | Earn/redeem/expire/reversal idempotent; saldo hasil sum ledger; double redeem ditolak. |
| M3.4 | Brosur promosi | campaign assets/export metadata | M3.2 | A4/A5 preview/export sesuai campaign; file private/authorized; placeholder jujur. |

### F4 — Reporting, performance, security, dan go-live hardening

| Milestone | Scope | Dependensi | Acceptance gate ringkas |
|---|---|---|---|
| M4.1 | Laporan lengkap dan export | F1–F3 sesuai report | Semua filter/total cocok ledger; export seluruh hasil filter, bukan current page; akses branch/role diuji. |
| M4.2 | Performance dan concurrency | M4.1 | Target p95 diuji dengan data representatif; query plan/index diverifikasi; retry/deadlock/idempotency test lulus pada DB target. |
| M4.3 | Security, privacy, observability | M4.2 | MFA privileged, rate limit, upload/PII masking, IDOR/cross-branch, dependency audit, logs/alerts dan failed jobs tervalidasi. |
| M4.4 | Staging, backup/restore, device UAT, go-live | M4.3 | Migration dry-run, backup+restore, printer/scanner, opening stock sign-off, cashier training, rollback runbook, dan pilot cash reconciliation lulus. |

## 8. Strategi pengujian dan quality gates

### 8.1 Lapisan pengujian

- **Unit:** Money, qty conversion, costing, pricing, tax, discounts, rounding, state transition, point rule.
- **Feature:** auth, validation, policy wiring, branch isolation, UI/endpoint response, localized error.
- **Integration:** database transaction lintas ledger/projection, PO receipt, invoice/AP, sale/payment/stock, returns, EOD.
- **Concurrency:** last-stock sale, double-click posting, sequence allocation, two AP payments, webhook duplicate.
- **Reconciliation:** movement vs balance, invoice/credit/payment vs AP, sale/refund vs payment, loyalty ledger vs balance.
- **Device/UAT:** barcode scanner, 58/80 mm printer, label readability, keyboard shortcuts, 1366×768 POS.
- **Performance:** scan/search p95, checkout DB p95, report/export query plan dengan volume representatif.

### 8.2 Gate wajib setiap milestone

1. Test paling sempit dijalankan selama pengembangan.
2. Test milestone, permission, validation, dan failure/rollback cases lulus.
3. PHP diformat dengan Pint bila ada perubahan PHP.
4. Frontend build lulus bila view/CSS/JS berubah.
5. Composer audit dijalankan bila dependency PHP berubah; audit npm dijalankan setelah lockfile tersedia bila dependency JS berubah.
6. Migration diuji fresh dan upgrade path bila schema berubah.
7. Tidak ada klaim lulus concurrency menggunakan SQLite; test kritis dijalankan pada MariaDB/InnoDB sebagai DB target.
8. Hasil perintah, jumlah test/assertion, masalah, dan keputusan diperbarui di `PROJECT_PROGRESS.md`.

## 9. Risiko utama dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Double posting karena retry/double-click | Idempotency key, unique source constraint, locked document, response replay. |
| Oversell pada kasir simultan | Stable-order `lockForUpdate`, validasi ulang available stock dalam transaction, DB target concurrency test. |
| Stok bertambah dua kali dari receipt dan invoice | Hanya receipt yang membuat receipt movement; invoice hanya mereferensikan receipt dan membuat financial/AP entry. |
| Margin berubah setelah master price/cost berubah | Freeze `cogs_unit_snapshot`, tax, discount, dan harga pada sale item. |
| AP outstanding negatif | Lock invoice/AP account, constraint allocation, transaction dan concurrency test. |
| QRIS pending dianggap paid | State machine pending/confirmed/failed/expired, callback tervalidasi, rekonsiliasi. |
| Cross-branch data leak | Mandatory branch scope, policy, scoped route binding/query, security tests. |
| SQLite menutupi perilaku locking produksi | Gunakan MariaDB/InnoDB untuk integration/concurrency gate. |
| Laporan lambat/tidak cocok ledger | Query objects read-only, indexes berdasarkan query, reconciliation fixtures, async export. |
| Scope melebar | Trace setiap task ke PRD/architecture/design; tambahan di luar dokumen memerlukan alasan dan persetujuan. |

## 10. Definition of Done

Sebuah fitur hanya boleh dinyatakan selesai bila:

- Requirement dan acceptance criteria milestone terpenuhi.
- Server-side validation, policy, branch scope, audit, dan idempotency diterapkan sesuai risiko.
- Posted data immutable dan jalur koreksi tersedia bila termasuk scope milestone.
- Automated tests yang relevan lulus, termasuk rollback/retry/concurrency untuk transaksi kritis.
- UI memakai Bahasa Indonesia, IDR, WIB, komponen reusable, accessibility basics, dan semua state utama.
- Query dipaginasi/diindeks sesuai kebutuhan; tidak ada N+1 yang diketahui pada jalur utama.
- Migration, endpoint/action, dan keputusan penting terdokumentasi.
- `PROJECT_PROGRESS.md` berisi bukti test, file/tabel yang berubah, masalah, dan keputusan terbuka.
- Agen berhenti dan menunggu instruksi **LANJUT**.

## 11. Titik mulai yang diizinkan berikutnya

M1.2 telah lulus pada MariaDB/InnoDB menggunakan database `retail_development` dan `retail_testing` yang terpisah. Migration development forward-only, nomor PO transaction-safe, draft dan snapshot historis, lifecycle approval dengan separation of duties, branch isolation/RBAC, audit/outbox, rollback/idempotency, concurrency dua proses, full regression suite, Blade compile, Pint, dan frontend build telah berhasil tanpa mutasi inventory atau AP. DEC-007 dan DEC-013 tetap OPEN untuk nominal approval dan kewajiban mapping barang-supplier. **STOP:** M1.3 tidak boleh dimulai sebelum instruksi eksplisit **LANJUT**.
