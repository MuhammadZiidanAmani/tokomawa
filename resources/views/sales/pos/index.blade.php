<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Kasir · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-100 text-slate-950">
        <main
            id="pos-app"
            data-lookup-url="{{ route('pos.lookup') }}"
            data-checkout-url="{{ route('pos.checkout') }}"
            data-shift-id="{{ $shift->id }}"
            class="flex min-h-dvh flex-col"
        >
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 shadow-sm lg:px-6">
                <div class="flex items-center gap-3">
                    <x-brand-logo />
                    <span class="hidden h-7 w-px bg-slate-200 sm:block"></span>
                    <div class="hidden sm:block">
                        <p class="text-xs text-slate-500">{{ $shift->register->name }} · {{ $shift->warehouse->name }}</p>
                        <p class="text-sm font-semibold">{{ $shift->cashier->name }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Shift aktif</span>
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
                </div>
            </header>

            <div class="grid flex-1 gap-4 p-3 lg:grid-cols-[minmax(0,1fr)_24rem] lg:p-4">
                <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-4">
                        <label for="pos-search" class="text-sm font-semibold text-slate-700">Pindai barcode atau cari barang <span class="font-normal text-slate-500">(F2)</span></label>
                        <div class="mt-2 flex gap-2">
                            <input id="pos-search" type="search" autocomplete="off" autofocus placeholder="Barcode, SKU, atau nama barang" class="min-h-12 flex-1 rounded-lg border border-slate-300 px-4 text-base focus:border-primary-600 focus:ring-primary-600">
                            <button id="pos-search-button" type="button" class="min-h-12 rounded-lg bg-primary-600 px-5 font-semibold text-white hover:bg-primary-700">Cari</button>
                        </div>
                        <p id="pos-message" class="mt-2 min-h-5 text-sm text-slate-500" aria-live="polite">Siap menerima pemindaian.</p>
                    </div>

                    <div id="pos-results" class="hidden border-b border-slate-200 bg-slate-50 p-3"></div>
                    <div class="min-h-52 flex-1 overflow-auto">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="px-4 py-3">Barang</th><th class="px-3 py-3">Qty</th><th class="px-3 py-3 text-right">Harga</th><th class="px-3 py-3 text-right">Subtotal</th><th class="px-3 py-3"><span class="sr-only">Aksi</span></th></tr>
                            </thead>
                            <tbody id="pos-cart" class="divide-y divide-slate-100"></tbody>
                        </table>
                        <div id="pos-empty" class="grid place-items-center px-4 py-16 text-center">
                            <div><p class="font-semibold text-slate-700">Keranjang masih kosong</p><p class="mt-1 text-sm text-slate-500">Pindai barcode atau gunakan pencarian barang.</p></div>
                        </div>
                    </div>
                </section>

                <aside class="rounded-xl bg-slate-950 p-5 text-white shadow-xl lg:sticky lg:top-4 lg:self-start">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Belanja</p>
                    <p id="pos-total" class="mt-2 text-4xl font-bold tracking-tight">Rp0</p>
                    <div class="my-5 h-px bg-white/10"></div>
                    <label for="cash-tendered" class="text-sm font-semibold text-slate-200">Uang diterima</label>
                    <input id="cash-tendered" type="number" min="0" step="0.01" inputmode="decimal" class="mt-2 min-h-12 w-full rounded-lg border border-slate-700 bg-slate-900 px-4 text-xl font-semibold text-white focus:border-primary-400 focus:ring-primary-400" placeholder="0">
                    <div class="mt-4 flex items-center justify-between text-sm"><span class="text-slate-400">Kembalian</span><strong id="pos-change" class="text-lg">Rp0</strong></div>
                    <p class="mt-5 rounded-lg bg-amber-400/10 px-3 py-2 text-xs text-amber-200">Harga, stok, dan HPP diverifikasi ulang oleh server saat checkout. Transaksi hanya tunai.</p>
                    <button id="pos-pay" type="button" disabled class="mt-5 min-h-14 w-full rounded-lg bg-primary-500 px-5 text-base font-bold text-white hover:bg-primary-400 disabled:cursor-not-allowed disabled:opacity-50">Bayar Tunai <span class="font-normal opacity-75">(F9)</span></button>
                </aside>
            </div>
        </main>

        <script>
            (() => {
                const root = document.getElementById('pos-app');
                const search = document.getElementById('pos-search');
                const searchButton = document.getElementById('pos-search-button');
                const results = document.getElementById('pos-results');
                const cartBody = document.getElementById('pos-cart');
                const empty = document.getElementById('pos-empty');
                const message = document.getElementById('pos-message');
                const totalLabel = document.getElementById('pos-total');
                const tenderedInput = document.getElementById('cash-tendered');
                const changeLabel = document.getElementById('pos-change');
                const payButton = document.getElementById('pos-pay');
                const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 });
                let cart = [];
                let submitting = false;

                const total = () => cart.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
                const keyFor = item => `${item.product_id}:${item.unit_id}:${item.inventory_batch_id || 0}`;
                const setMessage = (text, error = false) => { message.textContent = text; message.className = `mt-2 min-h-5 text-sm ${error ? 'text-danger-600' : 'text-slate-500'}`; };
                const render = () => {
                    empty.classList.toggle('hidden', cart.length > 0);
                    cartBody.innerHTML = cart.map((item, index) => `<tr>
                        <td class="px-4 py-3"><p class="font-semibold text-slate-900">${escapeHtml(item.name)}</p><p class="text-xs text-slate-500">${escapeHtml(item.sku)} · ${escapeHtml(item.unit_symbol)}${item.batch_label ? ` · Batch ${escapeHtml(item.batch_label)}` : ''}</p></td>
                        <td class="px-3 py-3"><input data-qty="${index}" type="number" min="0.001" step="0.001" value="${item.quantity}" class="w-24 rounded-lg border border-slate-300 px-2 py-2"></td>
                        <td class="px-3 py-3 text-right tabular-nums">${currency.format(item.price)}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums">${currency.format(Number(item.price) * Number(item.quantity))}</td>
                        <td class="px-3 py-3"><button data-remove="${index}" class="rounded-lg px-2 py-1 text-danger-600 hover:bg-red-50" aria-label="Hapus ${escapeHtml(item.name)}">Hapus</button></td>
                    </tr>`).join('');
                    const amount = total();
                    totalLabel.textContent = currency.format(amount);
                    const tendered = Number(tenderedInput.value || 0);
                    changeLabel.textContent = currency.format(Math.max(0, tendered - amount));
                    payButton.disabled = cart.length === 0 || tendered < amount || submitting;
                };
                const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
                const addItem = (item, batch = null) => {
                    if (!item.price) { setMessage(`Harga jual ${item.name} belum tersedia.`, true); return; }
                    if (item.tax_policy_blocked) { setMessage(`Checkout ${item.name} ditahan karena DEC-006 belum disahkan.`, true); return; }
                    const row = { ...item, inventory_batch_id: batch?.id || null, batch_label: batch?.lot_number || null, quantity: 1 };
                    const existing = cart.find(entry => keyFor(entry) === keyFor(row));
                    if (existing) existing.quantity = Number(existing.quantity) + 1; else cart.push(row);
                    results.classList.add('hidden'); search.value = ''; setMessage(`${item.name} ditambahkan.`); render(); search.focus();
                };
                const showResults = items => {
                    if (!items.length) { results.classList.add('hidden'); setMessage('Barang tidak ditemukan.', true); return; }
                    results.innerHTML = `<div class="grid gap-2">${items.map((item, index) => `<div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">${escapeHtml(item.name)}</p><p class="text-xs text-slate-500">${escapeHtml(item.sku)} · Stok ${escapeHtml(item.available_base_quantity)} · ${item.price ? currency.format(item.price) : 'Harga belum ada'}</p></div>${item.requires_batch ? `<select data-batch-result="${index}" class="min-h-10 rounded-lg border border-slate-300 px-3"><option value="">Pilih batch</option>${item.batches.map(batch => `<option value="${batch.id}">${escapeHtml(batch.lot_number || 'Tanpa lot')} · stok ${escapeHtml(batch.available_base_quantity)}${batch.expires_at ? ` · exp ${escapeHtml(batch.expires_at)}` : ''}</option>`).join('')}</select><button data-add-result="${index}" class="min-h-10 rounded-lg bg-primary-600 px-4 font-semibold text-white">Tambah</button>` : `<button data-add-result="${index}" class="min-h-10 rounded-lg bg-primary-600 px-4 font-semibold text-white">Tambah</button>`}</div>`).join('')}</div>`;
                    results.classList.remove('hidden');
                    results.querySelectorAll('[data-add-result]').forEach(button => button.addEventListener('click', () => {
                        const index = Number(button.dataset.addResult); const item = items[index]; let batch = null;
                        if (item.requires_batch) { const select = results.querySelector(`[data-batch-result="${index}"]`); batch = item.batches.find(candidate => String(candidate.id) === select.value); if (!batch) { setMessage('Pilih batch secara eksplisit.', true); return; } }
                        addItem(item, batch);
                    }));
                    if (items.length === 1 && !items[0].requires_batch) addItem(items[0]);
                };
                const performSearch = async () => {
                    const query = search.value.trim(); if (!query) return;
                    setMessage('Mencari barang…'); searchButton.disabled = true;
                    try { const response = await fetch(`${root.dataset.lookupUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'Pencarian gagal.'); showResults(payload.data || []); }
                    catch (error) { setMessage(error.message, true); }
                    finally { searchButton.disabled = false; }
                };
                searchButton.addEventListener('click', performSearch);
                search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); performSearch(); } });
                tenderedInput.addEventListener('input', render);
                cartBody.addEventListener('input', event => { if (event.target.dataset.qty === undefined) return; const index = Number(event.target.dataset.qty); cart[index].quantity = Math.max(0.001, Number(event.target.value || 0)); render(); });
                cartBody.addEventListener('click', event => { const button = event.target.closest('[data-remove]'); if (!button) return; cart.splice(Number(button.dataset.remove), 1); render(); });
                payButton.addEventListener('click', async () => {
                    if (submitting) return; submitting = true; render(); setMessage('Memposting penjualan secara atomik…');
                    const key = crypto.randomUUID();
                    try {
                        const response = await fetch(root.dataset.checkoutUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ cash_shift_id: Number(root.dataset.shiftId), cash_tendered: tenderedInput.value, idempotency_key: key, items: cart.map(item => ({ product_id: item.product_id, unit_id: item.unit_id, inventory_batch_id: item.inventory_batch_id, quantity: String(item.quantity) })) }) });
                        const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'Checkout gagal.'); window.location.assign(payload.url);
                    } catch (error) { submitting = false; setMessage(error.message, true); render(); }
                });
                document.addEventListener('keydown', event => { if (event.key === 'F2') { event.preventDefault(); search.focus(); } if (event.key === 'F9') { event.preventDefault(); if (!payButton.disabled) payButton.click(); } });
                render();
            })();
        </script>
    </body>
</html>
