# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Retail Management System — POS, Inventory, Purchasing & Loyalty
Versi: 1.0 | Tanggal: 22 Oktober 2026 | Status: Draft

## 1. Ringkasan Produk
Aplikasi retail terintegrasi untuk master data, pembelian, persediaan, POS, promosi, loyalitas, hutang supplier, dan laporan. Target: minimarket, toko retail, dan supermarket. Tujuan: stok akurat, transaksi cepat, audit jelas, dan laporan margin yang dapat dipercaya.

### Peran Pengguna
Super Admin, Owner, Manager Toko, Purchasing, Inventory/Gudang, Kasir, Finance, Marketing. Terapkan RBAC per modul, aksi, cabang, dan batas nominal approval.

## 2. Master Data
### MST-001 Master Barang
SKU unik, barcode pabrik/inhouse (multi-barcode), nama, merek, departemen, kategori, subkategori, satuan & konversi, supplier utama, rak, stok minimum, status pajak, harga beli, harga jual dan strata, status aktif. Batch/expiry bila diperlukan. Barang yang pernah bertransaksi tidak dihapus permanen; perubahan harga dicatat; stok tidak diedit langsung.
### MST-002 Master Supplier
Kode unik, nama, alamat, kontak, NPWP/NITKU bila relevan, PIC, rekening, termin, status, riwayat transaksi.
### MST-003 Barang per Supplier
Relasi many-to-many, kode barang supplier, harga terakhir/kontrak, MOQ, lead time, prioritas supplier.
### MST-004 Master Rak
Cabang/gudang, zona, lorong, kode rak, tingkat; barang dapat berada di banyak lokasi.
### MST-005 Master Konsumen/Member
Nomor member unik, identitas, telepon, kategori, tanggal bergabung, status, saldo poin turunan ledger, riwayat transaksi, persetujuan pemasaran.
### Master Pendukung
Departemen, Category, Sub Category, satuan, strata harga, jenis pembayaran, alasan retur, pajak, cabang/gudang, pengguna dan role.

## 3. Inventory & Purchasing
### INV-001 Pembelian PPN dan Non-PPN
Jenis cash, kredit, konsinyasi; supplier, nomor faktur unik per supplier, tanggal, item, qty, harga, diskon, perlakuan pajak, total. PPN dihitung berdasarkan konfigurasi dan peraturan yang berlaku. Posting penerimaan menambah stok; pembelian kredit membentuk hutang; cash mencatat pembayaran; konsinyasi memiliki kepemilikan stok dan settlement terpisah.
### INV-002 Purchase Order
Nomor otomatis contoh PO/2026/10/000001, supplier, item, qty, harga, estimasi tanggal, catatan. Status Draft, Approved, Sent, Partially Received, Completed, Cancelled. Approval dan cetak PO; nomor tidak boleh duplikat.
### INV-003 Penerimaan dari PO
Cari nomor PO, otomatis muat supplier dan item; masukkan nomor faktur, tanggal, qty diterima. Mendukung penerimaan parsial dan multi-penerimaan, toleransi kelebihan hanya dengan approval. Posting idempotent, menambah stok tepat satu kali.
### INV-004 Mutasi Hapus Barang Rusak
Dokumen bernomor, barang, qty, alasan, lokasi, petugas, approval; kurangi stok dan catat kerugian sesuai metode penilaian.
### INV-005 Banded dan Pecah Banded
Definisi BOM paket. Banded mengurangi komponen dan menambah SKU paket; pecah banded kebalikannya. Posting atomik, nilai HPP paket berasal dari komponen dan biaya relevan.
### INV-006 Retur ke Supplier
Referensi pembelian, alasan, item, qty, credit note/refund/penggantian; update stok dan hutang dengan jejak audit.
### INV-007 Stock Opname
Buat sesi, scope lokasi, snapshot stok, hitung fisik, recount, review selisih, approval, posting adjustment; kendalikan pergerakan selama opname; laporan selisih nilai.
### INV-008 Strata Harga
Retail, member, grosir, khusus; dapat berbasis jumlah, pelanggan dan periode; aturan prioritas dengan promosi.
### INV-009 Cetak Price Card
Template rak berisi nama, harga, satuan, barcode opsional; cetak massal.
### INV-010 Label Inhouse Barcode
Nomor unik, dukungan Code 128/EAN-13 sesuai validasi, template label dan printer barcode.
### INV-011 Pelunasan Faktur Supplier
Daftar invoice, jatuh tempo, pembayaran parsial/penuh, alokasi satu pembayaran ke banyak faktur, bukti bayar, status Unpaid/Partially Paid/Paid/Overdue, laporan saldo hutang.

## 4. Penjualan POS
### POS-001 Penjualan Kasir
Scan barcode, pencarian, qty, harga otomatis, member, hold/resume, diskon, hitung kembalian, split payment, struk 58/80 mm, stok turun setelah posting, shortcut keyboard, pembatasan stok negatif.
### POS-002 Retur Penjualan
Cari nota asli, pilih item/qty, alasan rusak/tidak sesuai/kedaluwarsa, refund/tukar barang, batas retur kumulatif; barang tak layak masuk karantina.
### POS-003 Diskon Manual
Nominal/persen, item/transaksi, batas per role, approval supervisor, audit log.
### POS-004 Void
Batal item/sebelum bayar; transaksi yang sudah diposting dibalik dengan dokumen reversal, alasan, approval, dan pemulihan stok/saldo yang benar.
### POS-005 Pembayaran
Cash, debit, QRIS, kredit/piutang, poin, voucher, dan split payment. Validasi status pembayaran noncash; QRIS yang belum terkonfirmasi tidak boleh otomatis dianggap lunas.
### POS-006 Laporan Penjualan dan Margin
Per transaksi atau periode: bruto, diskon, retur, neto, HPP historis, laba kotor, margin = laba kotor/penjualan neto × 100%.
### POS-007 End of Day
Buka/tutup shift, kas awal, penjualan per metode, kas masuk/keluar, refund, void, kas fisik, selisih, waktu dan petugas; shift tutup dikunci.
### POS-008 Business Hour
Omzet, jumlah transaksi, average basket per jam, filter tanggal, kasir, cabang.
### POS-009 Cetak Ulang Nota
Cari nota, cetak ulang berpenanda COPY, tanpa mengubah transaksi atau stok.

## 5. Promosi dan Loyalitas
### PRM-001 Promosi Periode
SKU, harga/diskon promo, mulai/akhir, prioritas dan aturan stacking. Harga POS dihitung saat transaksi sesuai periode; harga normal kembali otomatis setelah berakhir tanpa overwrite master.
### PRM-002 Promo Member
Harga khusus, bonus poin, diskon berdasarkan level member; cek eligibility dan aturan prioritas.
### PRM-003 Brosur Promosi
Pilih kampanye dan SKU, foto, harga normal/promo, hemat, template A4/A5, ekspor PDF.
### PRM-004 Tabungan Poin
Aturan earning/redeeming configurable, ledger immutable, expiry, reversal poin pada retur, batas saldo dan pencegahan double redeem.

## 6. Laporan
### RPT-001 Pembelian
Per periode, supplier, kategori, total, faktur; nilai sebelum pajak, pajak, diskon, total.
### RPT-002 Penjualan
Total; detail; pelanggan/member/nota; category/subcategory; kasir; kasir detail; supplier; ranking qty, value, margin; retur per periode; margin per transaksi; business hour; EOD. Atribusi supplier harus ditentukan (supplier utama atau batch asal) agar tidak ganda.
### RPT-003 Hutang Supplier
Per supplier/periode, saldo awal, pembelian kredit, retur/credit note, pembayaran, saldo akhir, aging dan jatuh tempo.
### RPT-004 Pelunasan Hutang
Per periode, supplier, faktur, bukti bayar, metode dan nominal.
### RPT-005 Poin Member
Saldo awal, earned, redeemed, expired, adjusted, saldo akhir per member/periode.
### RPT-006 Hasil Stock Opname
Stok sistem, fisik, selisih qty dan nilai, status approval per barang/sesi.
Semua laporan: filter periode, cabang, pencarian, pagination, ekspor PDF/Excel, akses sesuai role.

## 7. Alur Utama
1. Master barang/supplier → PO → approval → penerimaan → faktur → hutang/pembayaran → kartu stok.
2. Buka shift → scan barang → kalkulasi harga/promo/member → pembayaran terverifikasi → nota → stok/HPP/ledger → tutup shift.
3. Retur penjualan → verifikasi nota/qty → refund/tukar → stok layak/karantina → reversal poin dan laporan.
4. Stock opname → snapshot/hitung/recount → approval → adjustment → laporan.
5. Konsinyasi → penerimaan stok titipan → penjualan → laporan barang terjual → settlement supplier.

## 8. Aturan Bisnis Lintas Modul
- Semua mutasi stok ditulis ke stock_movements (ledger) dengan referensi transaksi, qty signed, lokasi, waktu, dan operator; saldo stok berasal dari ledger atau saldo terpelihara secara transaksional.
- Gunakan transaksi database atomik, locking saat update stok, idempotency key untuk pembayaran/posting, dan unique constraint untuk nomor dokumen.
- HPP: tentukan metode FIFO atau moving average sebelum implementasi; simpan HPP saat sale untuk laporan margin historis.
- Tidak boleh menghapus permanen dokumen posted; gunakan reversal/adjustment dan audit log.
- Tanggal efektif promo memakai timezone cabang; prioritas harga dan pembulatan rupiah didefinisikan eksplisit.
- Transaksi kredit pelanggan harus membentuk piutang; konsinyasi memisahkan stok titipan dari stok milik toko.
- Aturan PPN dan bukti pajak mengikuti konfigurasi serta regulasi terkini, tidak hardcode tarif.
- Retur dan void membalik stok, kas/piutang, hutang, poin, dan HPP sesuai kondisi, tanpa double posting.

## 9. Entitas Data Inti
users, roles, permissions, branches, warehouses, racks, departments, categories, subcategories, units, products, product_barcodes, product_units, suppliers, product_suppliers, members, price_tiers, product_prices, promotions, promotion_items, purchase_orders, purchase_order_items, goods_receipts, goods_receipt_items, purchase_invoices, purchase_invoice_items, supplier_payments, supplier_payment_allocations, supplier_returns, stock_balances, stock_movements, stock_opnames, stock_opname_items, bundles, bundle_components, bundle_movements, sales, sale_items, sale_payments, sale_returns, sale_return_items, cashier_shifts, cash_movements, member_point_ledger, vouchers, audit_logs.

## 10. Nonfunctional Requirements
- Respons scan POS target ≤ 500 ms pada jaringan lokal stabil, dan checkout normal target ≤ 3 detik (di luar latensi penyedia pembayaran).
- Transaksi finansial dan stok konsisten; tidak ada double posting; uang disimpan sebagai DECIMAL atau integer rupiah.
- HTTPS, password hashing, RBAC, CSRF protection, validasi server, rate limiting, audit log, backup terjadwal dan uji restore.
- Kompatibel desktop kasir dan tablet; printer thermal, barcode scanner keyboard wedge, printer label.
- Dashboard dan laporan dengan filter yang diindeks; paginasi untuk data besar.
- Kebijakan offline POS harus diputuskan; bila tidak tersedia, tampilkan pesan gangguan koneksi dan cegah transaksi yang tidak tersimpan.

## 11. Acceptance Criteria Utama
- PO nomor otomatis unik dan dapat dipanggil ke penerimaan; penerimaan parsial tidak menutup PO prematur.
- Cash/kredit/konsinyasi menghasilkan pencatatan stok dan kewajiban yang berbeda dan benar.
- Promo aktif tepat dalam periode; setelah berakhir harga normal dipakai kembali.
- Sale berhasil mengurangi stok sekali saja; gagal bayar tidak memposting sale lunas.
- Void dan retur tidak membuat stok atau refund ganda.
- Stock opname menghasilkan selisih qty/nilai dan adjustment hanya setelah approval.
- EOD merekonsiliasi kas teoretis dengan kas fisik.
- Hutang dan poin dapat direkonsiliasi dari ledger transaksi.
- Laporan margin menggunakan HPP historis, dan ekspor sesuai filter.

## 12. Prioritas Implementasi
**Fase 1 (MVP):** autentikasi/RBAC, master, barang/supplier/rak, PO, penerimaan, pembelian cash/kredit, ledger stok, POS cash, struk, laporan dasar, shift/EOD.
**Fase 2:** konsinyasi, retur supplier/penjualan, void, diskon approval, stock opname, banded, pelunasan hutang, QRIS/debit, strata harga, label.
**Fase 3:** member, poin, voucher, promosi otomatis, brosur, laporan lengkap, multi-cabang bila diperlukan.

## 13. Keputusan yang Perlu Dikonfirmasi
1. Satu toko atau multi-cabang/multi-gudang?
2. Metode HPP: FIFO atau moving average?
3. POS wajib tetap bekerja saat internet terputus?
4. QRIS/debit hanya pencatatan manual atau integrasi payment gateway/EDC?
5. Printer dan ukuran label yang digunakan?
6. Aturan strata harga, diskon bertumpuk, dan perolehan/penukaran poin?
7. Konsinyasi diselesaikan berdasarkan barang terjual atau jadwal kontrak?
8. Persetujuan untuk void, retur, diskon, dan stock adjustment?

## 14. Rekomendasi Teknis (Opsional)
Laravel + PHP 8.x yang didukung, MySQL/PostgreSQL, frontend Blade/Livewire atau Vue, Redis queue/cache bila diperlukan, role permissions, job scheduler, backup otomatis, dan integrasi perangkat kasir. Pilihan teknologi final ditetapkan pada dokumen Architecture.
