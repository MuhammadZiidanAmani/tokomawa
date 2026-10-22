# DESIGN.md — Retail Management System

**Produk:** Retail Management System (Master, Purchasing, Inventory, POS, Promo, Reports)  
**Versi:** 1.0 | **Tanggal:** 22 Oktober 2026 | **Status:** UI/UX specification for development  
**Acuan:** `PRD_Retail_Management_System.md` dan `architecture.md`  
**Bahasa utama UI:** Bahasa Indonesia | **Mata uang:** IDR (Rp) | **Zona waktu tampilan:** Asia/Jakarta

> Tujuan dokumen: menjaga konsistensi desain ketika dikerjakan manusia atau coding assistant. Seluruh komponen harus mengacu pada token dan pola di sini, bukan menciptakan warna, spacing, layout, tabel, atau modal baru secara acak.

## 1. Visi Pengalaman Pengguna

Aplikasi retail dipakai oleh dua konteks utama: (1) **back-office** untuk master, purchasing, stok, promosi, finance, dan laporan; (2) **POS front-counter** yang dioperasikan cepat menggunakan scanner barcode dan keyboard. Karena kebutuhannya berbeda, keduanya memakai design system yang sama namun layout kerja berbeda.

**Atribut desain:** modern, bersih, profesional, efisien, jelas, ringan, aman untuk transaksi finansial; tidak mengorbankan keterbacaan demi dekorasi.

**Prinsip UX:**
1. Hal terpenting terlihat dulu: kode barang, qty, harga, total, stok, status pembayaran.
2. Satu aksi utama per layar atau dialog; tombol berbahaya tidak disamakan secara visual dengan tombol biasa.
3. Pencarian cepat via barcode, nama, SKU, supplier, nomor dokumen.
4. Harga, stok, poin, dan nominal diisi/dihitung konsisten; setiap aksi destructive meminta alasan bila relevan.
5. User selalu mengetahui status dokumen (Draft/Pending/Posted/Cancelled) dan siapa terakhir mengubah.
6. Semua daftar utama punya loading, empty, validation, error, success, permission-denied, dan pagination states.
7. Setelah checkout sukses, struk boleh dicetak ulang tanpa memposting sale lagi.
8. Bahasa lugas bagi operator toko; jangan gunakan jargon developer dalam pesan kesalahan.

## 2. Arsitektur Informasi dan Navigasi

### 2.1 Sidebar back-office

```text
Dashboard
Master
  ├─ Barang
  ├─ Supplier
  ├─ Barang per Supplier
  ├─ Rak & Lokasi
  ├─ Member / Pelanggan
  ├─ Departemen / Kategori / Subkategori
  ├─ Satuan & Konversi
  └─ Strata Harga / Pajak
Pembelian
  ├─ Purchase Order
  ├─ Penerimaan Barang
  ├─ Faktur Pembelian
  ├─ Retur Supplier
  └─ Pembayaran Hutang Supplier
Inventory
  ├─ Ringkasan Stok
  ├─ Kartu Stok
  ├─ Barang Rusak / Write-off
  ├─ Banded / Pecah Banded
  ├─ Stock Opname
  ├─ Price Card
  └─ Cetak Label Barcode
Penjualan
  ├─ POS / Buka Kasir
  ├─ Riwayat Transaksi
  ├─ Retur Penjualan
  ├─ Void / Otorisasi
  └─ Shift & Tutup Kasir
Promosi
  ├─ Kampanye Promo
  ├─ Promo Member
  ├─ Brosur
  ├─ Voucher
  └─ Tabungan Poin
Laporan
  ├─ Penjualan & Margin
  ├─ Pembelian
  ├─ Stok & Stock Opname
  ├─ Hutang & Pelunasan
  ├─ Member & Poin
  └─ Business Hour / EOD
Pengaturan
  ├─ Cabang / Gudang / Register
  ├─ Akun & Hak Akses
  ├─ Persetujuan
  ├─ Template Cetak
  ├─ Nomor Dokumen
  └─ Audit Log
```

Tampilkan menu berdasarkan role/perizinan. Back-office dan POS memiliki navigasi yang terpisah supaya kasir tidak terganggu menu administratif.

### 2.2 Layout dasar back-office

```text
┌──────────── Sidebar (248 px) ──────────┬──────────────────────────────────────────┐
│ Logo + nama toko                       │ Topbar: Search | Cabang | Alert | User   │
│ Dashboard                              ├──────────────────────────────────────────┤
│ Master                                 │ Breadcrumb                                │
│ Pembelian                              │ Judul Halaman             [Aksi utama]   │
│ Inventory                              │ Deskripsi singkat / help text             │
│ Penjualan                              │ KPI cards (kalau relevan)                 │
│ Promosi                                │ Filter & Search Toolbar                   │
│ Laporan                                │ Data Table / Form / Detail                │
│ Pengaturan                             │ Pagination / Total summary               │
└────────────────────────────────────────┴──────────────────────────────────────────┘
```

- Sidebar desktop fixed/collapsible 248px expanded / 72px collapsed; topbar tinggi 64px.
- Main content max-width `1600px`; untuk layar besar gunakan whitespace terukur.
- Lebar grid halaman, panel, dan tabel responsif; tabel besar dapat horizontal-scroll tanpa menyembunyikan kolom aksi penting.
- Pada tablet sidebar menjadi drawer. Pada mobile back-office gunakan layout satu kolom dan kartu atau tabel ringkas.

## 3. Design Tokens

### 3.1 Warna tema utama

Tema direkomendasikan **Emerald Retail + Warm Amber**, bernuansa rapi/profesional; bukan kewajiban merek. Token satu sumber di CSS config agar mudah diganti mengikuti identitas toko.

| Token | Light | Fungsi |
|---|---|---|
| `--color-primary-50` | `#ECFDF5` | Highlight lembut |
| `--color-primary-100` | `#D1FAE5` | Hover subtle |
| `--color-primary-500` | `#10B981` | Aksen ilustrasi |
| `--color-primary-600` | `#059669` | Primary action |
| `--color-primary-700` | `#047857` | Primary hover / emphasis |
| `--color-primary-800` | `#065F46` | Heading brand/selected |
| `--color-accent-50` | `#FFFBEB` | Warning subtle |
| `--color-accent-500` | `#F59E0B` | Attention / promos |
| `--color-accent-700` | `#B45309` | Label promo di atas background terang |
| `--color-bg` | `#F8FAFC` | App background |
| `--color-surface` | `#FFFFFF` | Panel, dialog, table |
| `--color-surface-muted` | `#F1F5F9` | Secondary background |
| `--color-text` | `#0F172A` | Primary text |
| `--color-text-muted` | `#475569` | Supporting text |
| `--color-text-subtle` | `#64748B` | Metadata |
| `--color-border` | `#E2E8F0` | Borders/dividers |
| `--color-success` | `#047857` | Sukses, lunas |
| `--color-warning` | `#B45309` | Pending, warning |
| `--color-danger` | `#B91C1C` | Void, retur gagal, error |
| `--color-info` | `#1D4ED8` | Informasi, proses |

**Aksesibilitas:** cek kontras minimum WCAG 2.2 AA: umumnya 4.5:1 untuk teks normal dan 3:1 untuk teks besar/komponen nonteks. Hindari teks amber muda di atas putih; gunakan amber-700 untuk teks.

**Pola status:**
- Draft: neutral slate; Pending Approval: amber; Approved/Posted/Paid: emerald; Partial: blue; Overdue/Error/Cancelled: red (bedakan via teks, bukan warna saja); Expired: gray / warning sesuai konteks.
- Status `POSTED` bukan sinonim otomatis `PAID`: tampilkan status dokumen dan status pelunasan secara terpisah.

### 3.2 Dark mode (opsional setelah MVP)

Untuk fase awal fokus light theme yang kontras. Jika dark mode dibangun, definisikan token semantik, misalnya `--color-bg: #0B1220`, `--color-surface: #111827`, `--color-text: #F8FAFC`, `--color-border: #334155`. Jangan melakukan inversi warna sembarangan; pastikan status, chart, dan field tetap terbaca.

### 3.3 Tipografi

- Font: **Inter** atau font sans-serif sistem (fallback), bukan font dekoratif.
- Page title: 24px / line 32px / weight 700.
- Section title: 18px / line 28px / weight 650.
- Card title: 16px / line 24px / weight 600.
- Body: 14px / line 22px / weight 400.
- Helper/caption: 12px / line 18px.
- KPI value: 26–32px / weight 700; nominal pakai `font-variant-numeric: tabular-nums`.
- POS total: 32–40px / 700, jelas pada jarak pandang kasir.
- Jangan menggunakan huruf kapital penuh untuk paragraf/label panjang.

### 3.4 Skala jarak, radius, bayangan

**Spacing basis 4px:** `0=0`, `1=4`, `2=8`, `3=12`, `4=16`, `5=20`, `6=24`, `8=32`, `10=40`, `12=48`, `16=64` px. Semua padding/gap pakai token ini; jangan mengarang 13px/19px tanpa alasan.

| Elemen | Spesifikasi |
|---|---|
| Page horizontal padding | 24px desktop, 16px tablet, 12–16px mobile |
| Section vertical gap | 24–32px |
| Card padding | 20–24px; form card 24px |
| Card gap | 16px |
| Field gap | 6–8px label→input; 16px antar-field |
| Table row height | 44–48px; density mode compact 36–40px |
| Button/input height | 40px standard, 44px primary; 48px POS touch |
| Radius | `sm=6px`, `md=8px`, `lg=12px`, `xl=16px` |
| Card shadow | lembut: `0 1px 3px rgba(15,23,42,.08)` |
| Modal | Radius 12–16px; width 480px simple, 720px large |
| Divider | 1px solid border |

Tidak semua blok perlu shadow: table, card KPI, dialog utama saja. Background aplikasi netral.

### 3.5 CSS token awal

```css
:root {
  --color-primary: #059669;
  --color-primary-hover: #047857;
  --color-accent: #F59E0B;
  --color-bg: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-text: #0F172A;
  --color-text-muted: #475569;
  --color-border: #E2E8F0;
  --color-danger: #B91C1C;
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --space-8: 32px;
  --page-max-width: 1600px;
  --sidebar-width: 248px;
  --topbar-height: 64px;
}
```

## 4. Komponen UI yang Wajib Reusable

### 4.1 Components inventory

| Nama | Spesifikasi / states |
|---|---|
| `AppShell` | Sidebar, topbar, breadcrumb, active navigation |
| `PageHeader` | Title, subtitle, actions, breadcrumb |
| `PrimaryButton` | Default, hover, focus, loading, disabled |
| `SecondaryButton` | Cancel/secondary, outline |
| `DangerButton` | Void/delete/write-off; konfirmasi |
| `FormField` | Label, required, hint, error, prefix/suffix |
| `MoneyInput` | Tampilan format Rp, nilai numerik canonical |
| `QuantityInput` | Decimal disesuaikan unit, increment keyboard |
| `BarcodeInput` | Auto focus, enter scanner, feedback cepat |
| `SearchSelect` | Search supplier/produk/member, async, keyboard |
| `DateRangePicker` | WIB, inclusive UI, normalisasi query server |
| `StatusBadge` | Label dan semantics konsisten |
| `DataTable` | Sort, filter, pagination, selection, empty, loading |
| `FilterToolbar` | Search, date, supplier/category/status, reset |
| `ConfirmDialog` | Aksi permanen/berisiko, alasan wajib bila relevan |
| `ApprovalDialog` | Role/otorisasi, jejak penanggung jawab |
| `Drawer` | Detail ringan tanpa keluar konteks |
| `Toast/Alert` | Feedback aksi jelas dan nonmenyesatkan |
| `SummaryCard` | KPI nominal/perubahan periode, tooltip basis data |
| `PaymentSelector` | Cash/debit/QRIS/kredit/poin/voucher/split |
| `ReportExportMenu` | PDF/Excel, async result, permission |
| `PrintPreview` | Price card, barcode label, struk, A4 |
| `StockAvailability` | Sellable, reserved, quarantine, consignment |
| `TimelineAudit` | Actor, aksi, timestamp, before/after masked |
| `EmptyState` | No records/no result/permission denied berbeda |

**Ketentuan semua input:** jelas required/optional; tab order logis; `Enter` tidak mengirim form berbahaya tanpa konteks; error inline dekat field; skeleton untuk loading; disabled berbeda dari readonly; satu fokus keyboard terlihat jelas.

### 4.2 Tombol dan hierarki aksi

- Primary: satu aksi utama (`Simpan Draft`, `Posting`, `Bayar`, `Buat PO`) sesuai konteks, warna emerald.
- Secondary: `Kembali`, `Batal`, `Cetak`, `Reset Filter` (apabila bukan destructive).
- Destructive: `Void`, `Hapus Stok Rusak`, `Batalkan Dokumen` berwarna danger, dengan dialog konfirmasi.
- `Posting` lebih berisiko dari `Simpan Draft`: gunakan dialog konfirmasi ringkasan qty/uang dan penjelasan tidak bisa diedit langsung.
- Jangan memakai tombol `Simpan` yang ambigu bila tombol tersebut sebenarnya mengurangi stok/membuat hutang.

### 4.3 Data table umum

Urutan toolbar: judul/pilihan tab → search → quick filters → advanced filters → `Tambah`/`Export`. Kolom yang disarankan: nomor dokumen/ID, tanggal, nama/entitas, status, nilai, petugas, `Aksi` (sticky kanan). Angka rata kanan; teks rata kiri; status badge di tengah/kiri; tanggal `dd MMM yyyy, HH:mm` untuk timestamp, `dd MMM yyyy` untuk tanggal.

Pagination default 20 row; opsi 10/20/50/100. Ekspor seluruh data sesuai filter tidak boleh diam-diam hanya mengekspor current page; beri penjelasan. Untuk tabel banyak kolom, gunakan scroll horizontal dan opsi tampilkan/sembunyikan kolom.

### 4.4 Modal/konfirmasi bisnis

Contoh konfirmasi posting receiving:

```text
Posting Penerimaan Barang?
Nomor: GR/2026/10/00125
Supplier: PT Sumber Makmur
Jumlah item: 12 SKU
Total qty: 86 PCS

Setelah diposting, stok akan bertambah dan dokumen
harus dikoreksi melalui prosedur retur/adjustment.

                         [Kembali] [Posting Penerimaan]
```

Contoh void wajib alasan, menampilkan nomor struk asli, total, efek stok dan refund, meminta approval sesuai limit. Jangan gunakan browser `alert()` untuk keputusan finansial.

## 5. Dashboard Owner / Manager

### 5.1 Susunan

1. Filter global: cabang, tanggal/periode, refresh time.
2. KPI grid: Omzet Neto, Jumlah Transaksi, Laba Kotor, Margin %, Stok Rendah, Hutang Jatuh Tempo.
3. Grafik tren omzet vs periode lalu (line/bar) dengan label satuan.
4. Grafik Business Hour dan kategori terlaris.
5. Tabel ringkas Top Produk, Stock Alert, AP Due dan Pending Approval.
6. Tautan cepat ke detail yang benar-benar menggunakan filter halaman.

### 5.2 Tanda penting

- **Omzet** dibedakan dari pembayaran cash; sales QRIS/debit tetap bisa menjadi omzet tapi bukan kas fisik.
- **Margin** dihitung berdasarkan HPP historis; jika belum tersedia tampilkan “HPP belum lengkap”, bukan `0%` palsu.
- KPI setiap card punya definisi dan periode, misalnya “Penjualan Neto, 1–22 Okt 2026”.
- Tampilkan warning transaksi pending/rekonsiliasi bila berpengaruh ke dashboard.

## 6. Halaman Master

### 6.1 Master Barang

**List:** search barcode/SKU/nama, filter departemen/kategori/subkategori, supplier, active, stok minimum, aksi tambah/edit/detail/print label. Kolom minimum: SKU, Barang, Barcode Utama, Kategori, Satuan, Harga Jual, Stok Layak Jual, Status, Aksi.

**Form detail (tabs):**
- `Informasi Umum`: SKU, nama, brand, hierarki kategori, foto, satuan, barcode.
- `Supplier`: pilihan supplier utama, supplier lainnya, harga beli terakhir, MOQ.
- `Harga`: strata, masa berlaku, pajak, riwayat harga.
- `Stok & Lokasi`: stok per gudang/rak, minimum, batch/expiry.
- `Riwayat`: kartu stok, pembelian, penjualan, audit.

SKU yang sudah pernah transaksi bersifat stabil; ganti SKU bila diperlukan harus berjejak dan tidak mengubah snapshot masa lalu. Tidak ada input stok langsung pada tab ini.

### 6.2 Supplier

List + form identitas, PIC, termin pembayaran, rekening. Halaman detail memiliki tabs `Barang`, `PO`, `Pembelian`, `Retur`, `Hutang`, `Riwayat Pembayaran`.

### 6.3 Barang per Supplier

Tabel mapping dengan produk, supplier, kode supplier, harga terakhir, harga kontrak, MOQ, lead time, prioritas. Aksi `Jadikan Supplier Utama` memakai dialog konfirmasi; tidak menimpa HPP transaksi historis.

### 6.4 Rak

Tampilan list/tree `Cabang > Gudang > Zona > Lorong > Rak > Tingkat`. Item yang pindah lokasi menggunakan movement/transfer; jangan diam-diam mengubah rak jika stok onhand sudah ditempatkan di lokasi tertentu.

### 6.5 Member

Cari nama/no HP/no member; kartu ringkasan tier, saldo poin, tanggal bergabung, consent marketing. Detail tabs `Transaksi`, `Poin`, `Voucher`, `Retur`. Poin hanya readonly hasil ledger; manual adjustment lewat dialog berizin.

## 7. Halaman Purchasing dan Inventory

### 7.1 Purchase Order (PO)

**Form header:** nomor otomatis (readonly saat terbit), supplier search-select, tanggal PO, estimasi tiba, mata uang tetap IDR, catatan. **Lines:** SKU, nama, unit, qty, harga beli, diskon, pajak, subtotal; tombol tambah baris, scan, impor opsional.

**Sticky footer ringkasan:** jumlah SKU, qty, subtotal, diskon, PPN, grand total. Aksi: `Simpan Draft`, `Ajukan Persetujuan`, `Cetak PO` (hanya status memenuhi syarat). Status progress terlihat pada detail.

### 7.2 Penerimaan Barang dari PO

- Halaman awal menonjolkan input `Cari Nomor PO` dengan autocomplete.
- Setelah PO dipilih, nama supplier dan barang terisi otomatis. Supplier dari PO tidak boleh berubah sembarangan.
- Input `Nomor Faktur` dan `Tanggal Faktur` jika invoice masuk bersamaan; bila belum, izinkan receiving tanpa invoice jika kebijakan toko mendukung.
- Grid menampilkan `Qty PO`, `Sudah Diterima`, `Sisa`, `Qty Diterima Kali Ini`, `Selisih`.
- Over-receipt ditolak atau meminta persetujuan berdasarkan rule.
- Setelah posting, tampilkan nomor GR, ringkasan stok bertambah, serta link ke invoice dan kartu stok.

### 7.3 Pembelian / Faktur

Form menampilkan jenis `Tunai | Kredit | Konsinyasi` sebagai segmented control; pilihan memengaruhi field pembayaran/jatuh tempo/kontrak titip. Status pajak terlihat per line dengan ringkasan DPP/PPN; faktur tidak mengubah stok **kedua kali** jika stok sudah dinaikkan saat receipt.

### 7.4 Barang Rusak / Write-off

Pilih produk → pilih gudang/batch → qty → alasan → lampiran foto opsional → ringkasan kerugian berdasarkan HPP → ajukan persetujuan → posting. Tampilkan `Stok Layak Jual` sebelum/sesudah secara jelas.

### 7.5 Banded / Pecah Banded

Layout dua kolom: komponen asal (left), hasil paket (right); jumlah paket dan BOM version, total HPP. Tombol `Proses Banded`/`Pecah Banded` dengan modal konfirmasi. Jika sebagian komponen kurang, tampilkan daftar mana yang tidak mencukupi.

### 7.6 Retur ke Supplier

Pilih faktur atau GR asli; tampilkan qty yang boleh diretur; input alasan dan tujuan `Ganti Barang | Credit Note | Refund`. Tampilkan akibat stok dan AP; update status setelah dokumen posted.

### 7.7 Stock Opname

Stepper: `1. Buat Sesi` → `2. Hitung` → `3. Recount` → `4. Review Selisih` → `5. Persetujuan` → `6. Posting`.

Tabel: barcode/SKU, produk, stok sistem (opsional disembunyikan saat blind count), hitung fisik, qty selisih, nilai, hasil recount, catatan. Berikan indikator scope locked dan periode cutoff. Setelah posting, hasil final readonly dengan dokumen adjustment.

### 7.8 Price Card dan Barcode Inhouse

- Halaman select products (checkbox massal) + format kertas/label + jumlah copy.
- Live print preview untuk template label; dukung A4 sheet, thermal label, price card rak.
- Barcode harus diuji terbaca scanner, tidak hanya terlihat bagus pada layar.
- Tampilkan halaman preview berbeda antara `price card` dan `inhouse barcode`; jangan campur kedua fungsi.

### 7.9 Hutang Supplier

Daftar berdasarkan supplier, faktur, due date, outstanding, status aging. **Detail payment:** pilih supplier -> pilih faktur eligible -> input nominal dibayar per faktur -> total alokasi vs dana masuk -> metode dan bukti -> posting. Sisa saldo terlihat sebelum dan sesudah. Blok pembayaran melebihi outstanding.

## 8. POS Kasir — Desain Terpisah dan Keyboard-First

### 8.1 Layout POS desktop (1366×768 referensi)

```text
┌───────────────────────────────────────────────────────────────────────────────┐
│ Logo · Kasir 01 · Shift #128 · Cabang Utama           Member · Jam · Pengaturan │
├─────────────────────────────────────────┬─────────────────────────────────────┤
│ [ Scan barcode / cari barang...      ] │ Ringkasan Pembayaran                 │
│                                         │ Member: Umum / Pilih Member         │
│ CART: Nama      Qty     Harga    Total │ Subtotal                 Rp 118.000 │
│ ─────────────────────────────────────── │ Diskon                    Rp 8.000  │
│ Minyak Goreng    2     18.000   36.000 │ Pajak (bila berlaku)        ...     │
│ Gula 1KG         1     17.000   17.000 │ ──────────────────────────────────  │
│ dst...                                  │ TOTAL BAYAR              Rp110.000  │
│                                         │                                     │
│ [Hold] [Panggil Hold] [Diskon] [Batal] │ [BAYAR F9]                          │
└─────────────────────────────────────────┴─────────────────────────────────────┘
```

- POS menggunakan viewport penuh, tanpa sidebar back-office.
- Area cart ~62–68%, ringkasan ~32–38% desktop. Panel pembayaran sticky saat item panjang.
- Barcode input selalu mudah difokuskan kembali; barcode scanner umumnya memicu Enter, harus aman terhadap double input.
- Item baru memberikan sinyal visual singkat dan suara opsional yang bisa dimatikan.
- Harga dan nominal large enough; diskon/promo per item terlihat (bukan tersembunyi hanya di total).
- Status koneksi, shift, dan status payment external selalu terlihat.

### 8.2 Shortcut keyboard default (dapat dikonfigurasi)

| Shortcut | Aksi |
|---|---|
| `F2` | Fokus scan/pencarian barang |
| `F3` | Cari member |
| `F4` | Edit qty item terpilih |
| `F6` | Diskon (sesuai role) |
| `F7` | Hold transaksi |
| `F8` | Panggil hold |
| `F9` | Buka dialog pembayaran |
| `Esc` | Tutup dialog / batal action belum diposting |
| `Ctrl+P` | Cetak ulang hanya pada detail struk aktif |

Jangan memakai shortcut yang konflik kritis dengan browser/perangkat tanpa konfigurasi alternatif. Semua shortcut harus tersedia juga lewat tombol yang terlihat.

### 8.3 Dialog pembayaran

- Menampilkan total, poin/voucher eligible, pilihan metode.
- Cash: `Uang Diterima` dengan quick denominations (contoh 20rb/50rb/100rb sesuai total) dan hasil `Kembalian`.
- Debit: pilihan bank/EDC, referensi transaksi (jika manual), status konfirmasi.
- QRIS: jika terintegrasi, tampil QR dynamic dan status `Menunggu`, `Berhasil`, `Gagal`, `Kedaluwarsa`; jangan sukses karena user klik “Saya Sudah Bayar”.
- Kredit: pilih pelanggan yang memenuhi syarat, termin, limit, tanggal jatuh tempo.
- Poin: tampilkan nilai tukar, poin tersedia, poin yang dipakai, saldo setelah transaksi.
- Voucher: input kode, validasi real-time, terapkan aturan eligibility.
- Split: tampilkan baris tiap metode dan `Sisa Tagihan`; hanya posting bila semua komponen pembayaran yang memerlukan konfirmasi valid.

### 8.4 Konfirmasi sukses POS

Setelah commit, tampilkan `Transaksi Berhasil`, nomor nota, total, kembalian, metode pembayaran, serta `Cetak Nota` dan `Transaksi Baru`. Jika printer error, transaksi **tetap berhasil**, tampilkan peringatan “Transaksi tersimpan. Struk belum tercetak. Coba cetak ulang.” Jangan checkout ulang hanya untuk mencetak.

### 8.5 Retur penjualan

Flow: cari nomor nota → pilih baris, qty valid dan alasan → klasifikasi layak jual/karantina → refund/tukar → persetujuan bila perlu → cetak bukti. Jaga agar layar selalu menyajikan penjualan asli dan sisa kuantitas retur.

### 8.6 Void dan pembatalan

- Hapus baris belum diposting: dialog ringan bila cart sudah kompleks.
- Void sale posted: form terpisah dengan alasan wajib, otorisasi supervisor, pratinjau efek uang/stok/poin, nomor dokumen reversal.
- Beri label `VOIDED`, jangan menghapus nota asli dari riwayat.

### 8.7 Buka/Tutup Kasir (EOD)

**Open Shift:** pilih register, kas awal, kasir, tanggal/jam. **Close Shift:** report otomatis metode bayar, kas keluar/masuk, refund, cash expected, cash counted manual, selisih, alasan selisih, approval jika melebihi toleransi. Setelah closed tidak ada tombol edit transaksi lama.

### 8.8 Thermal receipt

Struk berisi nama/alamat toko (config), nomor nota, tanggal/jam, kasir, item/qty/harga/diskon, total, pajak bila relevan, pembayaran, kembalian, member/poin seperlunya, kebijakan retur. Ukuran 58/80mm; preview dan fallback print browser. Cetak ulang memberi label `COPY` dan timestamp cetak ulang.

## 9. Promosi, Member, Brosur

### 9.1 Promo periode

Form: nama kampanye, start/end WIB (jelas jamnya), status, scope cabang, tipe diskon/harga baru, SKU eligible, minimum qty, audience/tier, prioritas, stackability, batas penggunaan. Perlihatkan pratinjau harga efektif pada sample SKU; konflik promosi ditandai sebelum dipublikasikan.

**Status UI:** `Scheduled`, `Active`, `Expired`, `Paused`, `Cancelled`; dihitung konsisten terhadap waktu server + timezone operasional. Jangan melakukan overwrite harga retail master.

### 9.2 Promo member dan tabungan poin

Pada detail member tampilkan saldo poin, poin akan kedaluwarsa, earning/redeeming history per transaksi. Jangan membuat poin menjadi nominal rupiah tanpa menyebut kurs yang berlaku. Adjustment manual berizin dan alasan wajib.

### 9.3 Brosur promo

Wizard `Pilih Kampanye -> Pilih Produk -> Pilih Template -> Pratinjau -> Export PDF`. Konten brosur: foto, nama, harga lama (jika benar), harga promo, rentang berlaku, syarat singkat, identitas toko. Pastikan teks tetap terbaca pada A4/A5 cetak. Jika foto tidak ada, placeholder tidak boleh tampak seperti gambar asli produk.

## 10. Laporan — UI dan Interaksi

### 10.1 Pola universal

Header report, filter periode (default hari ini untuk POS atau 30 hari untuk tren), cabang, supplier/kategori/kasir/member bila relevan, `Terapkan Filter`, `Reset`, `Export`. Di bawahnya summary KPI + tabel + chart hanya saat chart benar-benar menambah insight.

Contoh report penjualan dan margin:

```text
Laporan Penjualan & Margin             [Export Excel] [Export PDF]
[Periode: 01-22 Okt] [Cabang] [Kasir] [Kategori] [Terapkan]
┌───────────────┬───────────────┬──────────────┬────────────┐
│ Penjualan Neto│ HPP           │ Laba Kotor   │ Margin %   │
└───────────────┴───────────────┴──────────────┴────────────┘
Tanggal | No Nota | Kasir | Omzet Neto | HPP | Laba | Margin
...
```

### 10.2 Laporan yang didukung

- Pembelian: periode, supplier, kategori, total, per faktur.
- Penjualan: total, detail, member/nota, kategori/subkategori, kasir ringkas/detail, supplier, ranking qty/value/margin, retur.
- Hutang supplier: outstanding, termin, aging, saldo awal/akhir.
- Pelunasan hutang: bukti bayar, metode, alokasi faktur.
- Poin member: mutasi poin dan saldo.
- Stock opname: selisih qty, nilai dan approval.
- Business Hour: jam, transaksi, omzet, average ticket; gunakan jam WIB jelas.
- EOD: cash expectation/count, tender mix, selisih dan approver.

### 10.3 Format, sorting, dan ekspor

- Nilai IDR: `Rp 1.250.000` di layar; untuk tabel angka bisa memakai `Rp1.250.000`, tetap konsisten. Nilai negatif `-Rp40.000`; gunakan locale `id-ID` dan pemrosesan raw decimal, bukan string formatted saat kirim API.
- Persentase: mis. `24,50%`; jangan membagi dengan 0, tampilkan `—` bila denominator 0.
- Kuantitas: 2 atau 2,500 sesuai aturan unit; hindari 2.000 berarti ambigu (ribuan vs pecahan).
- Sorting angka berdasar nilai numeric, bukan string.
- PDF export berheader identitas, filter, generated-at WIB, page no, grand total. Excel raw table dengan metadata/sheet summary opsional.
- Informasi “Ditampilkan X dari Y transaksi” wajib bila pagination.

## 11. Aksesibilitas dan Responsivitas

| Breakpoint | Layout |
|---|---|
| `< 640px` | Back-office single-column; action sticky; table compact/cards |
| `640–1023px` | Tablet layout, drawer sidebar, filter wrap |
| `>= 1024px` | Desktop sidebar + multi-column; POS split pane |
| `>= 1440px` | Peningkatan whitespace/kolom, tidak memperbesar semuanya |

- Target min 44×44px untuk kontrol sentuh penting POS (atau spacing memadai sesuai WCAG).
- Focus ring jelas; form bisa dengan keyboard; modal trap focus dan restore focus setelah tutup.
- Semua ikon aksi yang tidak jelas diberi label/tooltip dan `aria-label`.
- Status selain warna memiliki teks/ikon; error diidentifikasi label dan penjelasan.
- Posisi tooltip, dropdown, dan toast tidak menutup scanner input atau tombol Bayar.
- Hormati reduced-motion; animasi maksimal 150–250 ms untuk transisi ringan.

## 12. UX States, Notifications, dan Microcopy

| Kondisi | Pola feedback |
|---|---|
| Data disimpan draft | Toast “Draft PO berhasil disimpan.” |
| Posting sukses | Halaman receipt/detail dengan nomor dan status terposting |
| Barcode tidak ditemukan | Inline “Barcode belum terdaftar. Cari nama barang atau hubungi admin.” |
| Stok kurang | Error “Stok tersedia 3 PCS; permintaan 5 PCS.” dan fokus qty |
| Harga berubah sejak quote | Dialog “Harga diperbarui. Periksa kembali total pembayaran.” |
| QRIS masih pending | Panel progress + opsi batalkan/cek ulang, bukan toast sukses |
| Print gagal | “Transaksi tersimpan, struk belum tercetak. Cetak ulang.” |
| Kredit supplier melewati jatuh tempo | Badge “Terlambat X hari” |
| Izin kurang | “Anda tidak memiliki izin melakukan void. Minta persetujuan supervisor.” |
| Ekspor besar | Notifikasi progress “Laporan sedang disiapkan” di dalam aplikasi |
| Server tidak tersambung | Banner “Koneksi terputus. Transaksi belum dikirim.” jangan anggap posted |

**Aturan:** tidak boleh memakai “Berhasil” sebelum server memastikan commit; tidak boleh menyembunyikan error payment/stok dengan loading tanpa batas. Form error harus mempertahankan input user yang aman untuk diulang.

## 13. Aturan Akses/Approval dalam Desain

| Aksi | Kasir | Manager | Finance | Super Admin |
|---|---|---|---|---|
| Jual POS & cetak nota | Ya | Sesuai izin | Sesuai izin | Ya |
| Diskon manual dalam limit | Ya | Ya | Sesuai izin | Ya |
| Diskon di atas limit | Ajukan | Setujui sesuai limit | Tidak otomatis | Sesuai izin |
| Void posted | Ajukan | Setujui | Tidak otomatis | Sesuai izin |
| Stok write-off | Tidak | Setujui | Lihat dampak nilai | Sesuai izin |
| Supplier payment | Tidak | Lihat/approve | Buat & proses | Sesuai izin |
| Ubah master pajak | Tidak | Tidak | Sesuai izin | Ya |

Matriks ini usulan awal, disahkan pemilik toko sebelum produksi. Sembunyikan aksi yang tidak relevan, tetapi seluruh API tetap wajib cek permission server-side.

## 14. Print, Scan, dan Perangkat

1. **Scanner USB:** mode keyboard wedge; auto focus barcode input; parsing Enter/TAB scanner; cegah scan dobel tidak sengaja.
2. **Printer thermal:** desain CSS receipt untuk 58 dan 80 mm; uji karakter panjang, line wrapping, pajak, diskon, dan copy label.
3. **Label barcode:** tentukan kode simpanan, check digit jika EAN-13, kualitas cetak 203/300 DPI dan test scan fisik.
4. **Preview/Retry:** selalu tersedia; print error tidak membuat transaksi ganda.
5. **Print bridge:** apabila dibutuhkan silent printing, gunakan local helper yang dipercaya, dengan pairing printer, autentikasi lokal, dan pembatasan origin. Jangan membuka endpoint print publik tanpa auth.

## 15. Performance UI

- Debounce search nama 200–300ms; barcode scan harus langsung resolve setelah Enter tanpa debounce berlebihan.
- Untuk daftar SKU >10 ribu, gunakan server-side search/pagination, virtual list bila perlu; hindari memuat semua produk di browser.
- Cart menyimpan snapshot ringan di browser, tapi total akhir divalidasi authoritative server.
- Tabel laporan query saat filter diterapkan; jangan auto-fetch report berat setiap perubahan satu input.
- Skeleton pada data asinkron; tombol posting disabled saat pending request; idempotency menjaga backend terhadap double-click.
- Foto produk lazy load dan gunakan ukuran thumbnail. Jangan memuat grafik dashboard saat kasir berada di mode POS.

## 16. Contoh Pola Halaman berdasarkan Modul

### 16.1 Master listing

`PageHeader -> FilterToolbar -> DataTable -> Pagination -> Drawer/Detail`. CRUD form = `2-column form desktop`, dengan primary actions sticky di akhir.

### 16.2 Dokumen pembelian

`Breadcrumb -> Header with status -> Supplier + Invoice metadata -> Editable line table -> Summary totals -> Activity/Audit -> Actions`.

### 16.3 Dokumen posted (read-only)

Header status, tanggal, pencipta, approver, referensi PO/GR/invoice/nota, tabel item, ledger effects, timeline. Tidak ada editor inline untuk nilai posted. Tampilkan aksi `Cetak`, `Retur`, `Buat Koreksi` yang diizinkan.

### 16.4 Halaman list stok

Filter cabang/gudang/rak, status stok, min-stock; tabel stok layak, reserved, quarantine, consignment, satuan, nilai persediaan (hanya role yang boleh). Klik baris membuka kartu stok dan batch.

### 16.5 Detail laporan

Filter di atas, KPI/breakdown, drill-down table, export; apabila report margin, opsi subtotal group dan total akhir harus bisa dilacak ke transaksi.

## 17. Komponen Tailwind/Blade/Livewire (Panduan Implementasi)

Penamaan yang disarankan:

```text
<x-app-shell>
<x-page-header>
<x-ui.button variant="primary|secondary|danger">
<x-ui.input>
<x-ui.money-input>
<x-ui.status-badge>
<x-ui.data-table>
<x-ui.empty-state>
<x-ui.confirm-dialog>
<x-pos.barcode-search>
<x-pos.cart-table>
<x-pos.payment-dialog>
<x-inventory.stock-indicator>
<x-reports.filter-toolbar>
```

Pola Livewire backend modules:

```text
Livewire/Master/Products/{ProductIndex,ProductForm,ProductShow}
Livewire/Purchasing/{PoIndex,PoForm,PoShow,ReceivingForm}
Livewire/Inventory/{StockOverview,StockCountForm,AdjustmentForm}
Livewire/Pos/{Register,PaymentDialog,SaleHistory,ShiftClose}
Livewire/Promotions/{CampaignIndex,CampaignForm,MemberPointLedger}
Livewire/Reports/{SalesReport,PurchaseReport,StockReport,ApReport}
```

Jangan langsung menanam `<div style=...>` acak berulang dalam view; gunakan Tailwind class konsisten atau komponen. Gunakan semantic CSS vars untuk warna, dan satu helper formatter currency/date agar seluruh modul sama.

## 18. UX Acceptance Criteria per Fitur

| Skenario | Perilaku yang harus lulus |
|---|---|
| Cari barang scan | Barcode valid menambah item tepat sekali; fokus kembali ke scan |
| Item tak ditemukan | Feedback singkat; tidak menghapus cart lama |
| Edit qty | Total, diskon dan pajak dihitung ulang; stok tetap divalidasi saat checkout |
| PO dipanggil | Supplier dan line PO muncul; `qty outstanding` jelas |
| Receiving parsial | Sisa PO tetap terlihat dan dapat diterima kemudian |
| Invoice duplikat | Form menampilkan field konflik dan tidak membuat hutang baru |
| Write-off | Preview nilai kerugian dan approval sebelum posting |
| Stock opname | Selisih qty & nilai tampak, approval terakhir tercatat |
| Promo expired | POS memakai harga normal kembali tanpa edit manual master |
| Member sale | Harga member/poin eligible transparan |
| QRIS pending | Tidak ada nota “Lunas” sampai pembayaran terkonfirmasi |
| Split payment | Sisa bayar = 0 dan semua metode eligible confirmed sebelum posted |
| Print error | Sale tetap tercatat satu kali, pengguna dapat retry print |
| Void posted | Nota asli tetap ada, dokumen reversal dapat ditelusuri |
| EOD | Selisih kas dan detail pembayaran kasir jelas, shift closed terkunci |
| Export | Isi report sesuai filter dan jumlah records, bukan current page saja |

## 19. Larangan Desain (Anti-patterns)

- Jangan membuat setiap halaman memakai warna/background/card yang berbeda.
- Jangan menampilkan stok total gabungan tanpa membedakan stok layak, karantina, dan konsinyasi.
- Jangan menjadikan diskon manual sebagai field bebas tanpa batas hak akses.
- Jangan membuat tombol posting sama tidak mencoloknya dengan simpan draft.
- Jangan mengandalkan placeholder sebagai satu-satunya label input.
- Jangan menampilkan teks status `Lunas` untuk pembayaran gateway `Pending`.
- Jangan menghitung ulang margin historis memakai harga beli atau harga jual master saat ini.
- Jangan memaksa kasir membuka dashboard admin untuk transaksi berikutnya.
- Jangan memunculkan dialog konfirmasi setiap barcode discan; hanya untuk aksi berisiko.
- Jangan membuat tanggal promosi ambigu soal akhir hari/jam/timezone.

## 20. Checklist Konsistensi untuk AI Coding Assistant

Sebelum membuat atau mengubah halaman, AI harus memeriksa:

- [ ] Halaman berada pada menu, role, dan modul yang tepat.
- [ ] Memakai `AppShell`, token warna, typography, dan spacing dari dokumen ini.
- [ ] Menggunakan komponen reusable bila sudah ada.
- [ ] Tombol primary/secondary/destructive jelas.
- [ ] State loading, empty, error, success, disabled, dan permission ada.
- [ ] Tabel punya pencarian, filter, sorting, pagination bila perlu.
- [ ] Semua uang, qty, persentase dan tanggal menggunakan formatter Indonesia konsisten.
- [ ] Aksi posting/void/retur menampilkan konfirmasi dan otorisasi yang benar.
- [ ] Mobile/tablet back-office dan resolusi kasir 1366×768 diuji.
- [ ] Barcode scan dan keyboard flow diprioritaskan untuk POS.
- [ ] Respons UI tidak menyatakan sukses sebelum transaksi server commit.
- [ ] Print retry aman dan tidak membuat sale ulang.
- [ ] Tidak ada transaksi finansial maupun stock movement langsung di layer UI.
- [ ] Lulus accessibility basics (focus, labels, contrast, keyboard).

## 21. Prompt Implementasi Awal (Siap Digunakan)

Gunakan prompt ini setelah ketiga file diletakkan di root/repo aplikasi:

```text
Kamu adalah senior Laravel architect, product engineer, dan UI/UX engineer.
Baca tiga dokumen terlebih dahulu: PRD_Retail_Management_System.md,
architecture.md, dan design.md. Perlakukan semuanya sebagai source of truth.

Tujuan: bangun aplikasi Retail Management System sebagai modular monolith
Laravel dengan Blade, Livewire, Tailwind CSS, database relasional,
dan pengelolaan transaksi stok/uang yang dapat diaudit.

Aturan wajib:
1. Mulai dari F0: Auth + RBAC, cabang, master pendukung, nomor dokumen,
   audit log, reusable UI. Jangan membuat semua modul sekaligus.
2. Untuk setiap fitur tuliskan: kebutuhan, skema tabel + migration,
   model, policy, action/service, UI, test dan acceptance criteria.
3. Semua posting stok/penjualan/AP/loyalty harus dalam database transaction,
   concurrency-safe dan idempotent.
4. Jangan mengubah stok langsung di master barang. Semua lewat ledger.
5. Jangan menganggap pembayaran QRIS berhasil tanpa status confirmed.
6. Ikuti semua token dan pola UI dalam design.md, gunakan komponen reusable.
7. Pakai satu kebijakan harga/costing/pajak yang konsisten dan terdokumentasi.
8. Terapkan scopes cabang, validation, permission, audit untuk semua aksi.
9. Jangan menghapus/mengubah data posted; gunakan reversal/koreksi.
10. Buat test otomatis untuk transaksi kritis dan skenario retry/concurrency.

Kerjakan fase F0 dulu. Tampilkan usulan file/migrations/ERD, matriks izin,
dan urutan pekerjaan. Setelah implementasi, tampilkan hasil test dan
file yang dibuat/diubah, serta keputusan/asumsi yang belum dipastikan.
```

## 22. Keputusan Visual dan Operasional yang Masih Terbuka

1. Logo, nama toko, dan warna merek final (token di atas adalah rekomendasi default).
2. Printer thermal, label printer, barcode scanner, dan OS komputer kasir.
3. Apakah POS perlu touchscreen utama atau keyboard scanner sebagai metode dominan.
4. Apakah kasir dapat menjual barang dengan stok minus / preorder.
5. Apakah toko memiliki beberapa gudang, beberapa kasir, atau multi-cabang.
6. Apakah promo dan voucher bisa ditumpuk, serta diskon maksimum per role.
7. Apakah laporan HPP dan margin terlihat oleh Manager atau hanya Owner.
8. Ukuran price card, desain struk, dan tata letak brosur promosi sesuai merek.

---

**Prinsip akhir:** desain harus mempercepat pekerjaan kasir, mempermudah keputusan manajer, dan tidak menyesatkan mengenai stok, status pembayaran, harga, atau margin. Konsistensi jauh lebih penting daripada ornamen UI.
