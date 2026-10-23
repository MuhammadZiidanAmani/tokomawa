@php
    $editing = $goodsReceipt !== null;
    $selectedPurchaseOrder = (string) old('purchase_order_id', $goodsReceipt?->purchase_order_id ?? '');
    $initialItems = old('items', $editing
        ? $goodsReceipt->items->map(fn ($item) => [
            'purchase_order_item_id' => $item->purchase_order_item_id,
            'quantity' => $item->quantity,
            'lot_number' => $item->lot_number_snapshot,
            'manufactured_at' => $item->manufactured_at_snapshot?->format('Y-m-d'),
            'expires_at' => $item->expires_at_snapshot?->format('Y-m-d'),
            'notes' => $item->notes,
        ])->values()->all()
        : []);
@endphp

<x-layouts.app :title="$editing ? 'Edit Penerimaan Barang' : 'Buat Penerimaan Barang'">
    <form
        method="POST"
        action="{{ $editing ? route('purchasing.goods-receipts.update', $goodsReceipt) : route('purchasing.goods-receipts.store') }}"
        class="grid gap-6"
        x-data="{
            purchaseOrders: {{ Illuminate\Support\Js::from($purchaseOrders) }},
            purchaseOrderId: {{ Illuminate\Support\Js::from($selectedPurchaseOrder) }},
            items: {{ Illuminate\Support\Js::from($initialItems) }},
            submitting: false,
            purchaseOrder() { return this.purchaseOrders.find(po => String(po.id) === String(this.purchaseOrderId)); },
            sourceLine(item) { return this.purchaseOrder()?.items?.find(line => String(line.id) === String(item.purchase_order_item_id)); },
            loadOutstanding() {
                const po = this.purchaseOrder();
                this.items = po ? po.items.map(line => ({ purchase_order_item_id: line.id, quantity: line.outstanding, lot_number: '', manufactured_at: '', expires_at: '', notes: '' })) : [];
            },
            removeItem(index) { this.items.splice(index, 1); }
        }"
        x-on:submit="submitting = true"
    >
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $formKey) }}">

        <x-page-header :title="$editing ? 'Edit Draft Penerimaan' : 'Buat Penerimaan Barang'" description="Draft tidak mengubah stok atau outstanding PO. Semua nilai divalidasi ulang saat posting.">
            <x-slot:actions>
                <x-ui.button :href="$editing ? route('purchasing.goods-receipts.show', $goodsReceipt) : route('purchasing.goods-receipts.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button type="submit" x-bind:disabled="submitting"><span x-show="!submitting">Simpan Draft</span><span x-show="submitting" x-cloak>Menyimpan...</span></x-ui.button>
            </x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <x-ui.alert variant="danger"><p class="font-semibold">Draft belum dapat disimpan.</p><ul class="mt-2 list-disc pl-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
        @endif

        <x-ui.card title="Referensi Penerimaan" description="Supplier dan item selalu mengikuti snapshot Purchase Order.">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="grid gap-1.5">
                    <label for="purchase_order_id" class="text-sm font-semibold text-slate-700">Purchase Order</label>
                    @if ($editing)<input type="hidden" name="purchase_order_id" value="{{ $selectedPurchaseOrder }}">@endif
                    <select id="purchase_order_id" name="purchase_order_id" x-model="purchaseOrderId" x-on:change="loadOutstanding()" @disabled($editing) required class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-100">
                        <option value="">Cari / pilih Purchase Order</option>
                        @foreach ($purchaseOrders as $po)<option value="{{ $po['id'] }}">{{ $po['number'] }} — {{ $po['supplier'] }} ({{ $po['status'] }})</option>@endforeach
                    </select>
                </div>
                <x-ui.select name="warehouse_id" label="Gudang Penerimaan" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $goodsReceipt?->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach
                </x-ui.select>
                <x-ui.input name="supplier_delivery_note" label="Nomor Surat Jalan" :value="old('supplier_delivery_note', $goodsReceipt?->supplier_delivery_note)" required />
                <x-ui.input name="received_at" type="datetime-local" label="Waktu Diterima" :value="old('received_at', $goodsReceipt?->received_at?->timezone(config('retail.display_timezone'))->format('Y-m-d\TH:i') ?? now()->timezone(config('retail.display_timezone'))->format('Y-m-d\TH:i'))" required />
            </div>
            <div class="mt-4 grid gap-1.5"><label for="notes" class="text-sm font-semibold text-slate-700">Catatan</label><textarea id="notes" name="notes" rows="3" class="rounded-lg border border-slate-300 px-3 py-2">{{ old('notes', $goodsReceipt?->notes) }}</textarea></div>
        </x-ui.card>

        <x-ui.card title="Item Diterima" description="Over-receipt ditolak. Batch/expiry wajib mengikuti konfigurasi barang.">
            <div x-show="!purchaseOrderId" class="rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500">Pilih Purchase Order untuk memuat item outstanding.</div>
            <div x-show="purchaseOrderId && items.length === 0" x-cloak class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-6 py-8 text-center text-sm text-amber-800">Tidak ada item dipilih. Pilih ulang PO untuk memuat semua outstanding.</div>
            <div class="grid gap-4">
                <template x-for="(item, index) in items" :key="item.purchase_order_item_id">
                    <section class="rounded-xl border border-slate-200 p-4">
                        <input type="hidden" x-bind:name="`items[${index}][purchase_order_item_id]`" x-bind:value="item.purchase_order_item_id">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-xs font-semibold text-primary-700" x-text="sourceLine(item)?.sku"></p><h3 class="font-semibold text-slate-950" x-text="sourceLine(item)?.name"></h3><p class="text-xs text-slate-500" x-text="`PO ${sourceLine(item)?.ordered ?? '—'} · diterima ${sourceLine(item)?.received ?? '—'} · sisa ${sourceLine(item)?.outstanding ?? '—'} ${sourceLine(item)?.unit ?? ''}`"></p></div><button type="button" x-on:click="removeItem(index)" class="min-h-10 rounded-lg px-3 text-sm font-semibold text-danger-600 hover:bg-danger-50">Hapus Baris</button></div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700">Qty diterima</label><input type="number" min="0.001" step="0.001" required x-model="item.quantity" x-bind:max="sourceLine(item)?.outstanding" x-bind:name="`items[${index}][quantity]`" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums"></div>
                            <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700">Nomor batch</label><input type="text" x-model="item.lot_number" x-bind:required="sourceLine(item)?.track_batch" x-bind:disabled="!sourceLine(item)?.track_batch" x-bind:name="`items[${index}][lot_number]`" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-100"></div>
                            <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700">Tanggal produksi</label><input type="date" x-model="item.manufactured_at" x-bind:disabled="!sourceLine(item)?.track_batch" x-bind:name="`items[${index}][manufactured_at]`" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-100"></div>
                            <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700">Kedaluwarsa</label><input type="date" x-model="item.expires_at" x-bind:required="sourceLine(item)?.track_expiry" x-bind:disabled="!sourceLine(item)?.track_batch" x-bind:name="`items[${index}][expires_at]`" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-100"></div>
                            <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700">Catatan item</label><input type="text" x-model="item.notes" x-bind:name="`items[${index}][notes]`" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2"></div>
                        </div>
                    </section>
                </template>
            </div>
        </x-ui.card>
    </form>
</x-layouts.app>
