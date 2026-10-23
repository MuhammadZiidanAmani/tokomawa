<x-layouts.app title="Saldo Awal">
    <div class="grid gap-6">
        <x-page-header title="Saldo Awal Persediaan" description="Ajukan saldo awal untuk disetujui. Stok baru berubah setelah approval dan posting atomik.">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('inventory.index')">Kembali</x-ui.button></x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-ui.alert variant="success" title="Berhasil">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" title="Periksa kembali formulir">{{ $errors->first() }}</x-ui.alert>
        @endif

        @can('inventory.opening.create')
            <x-ui.card title="Input saldo awal" description="Gunakan nilai HPP per satuan dasar. Dokumen tidak dapat diedit setelah diposting.">
                <form
                    method="POST"
                    action="{{ route('inventory.opening.store') }}"
                    class="grid gap-5"
                    x-data="openingBalanceForm({{ Js::from($products->map(fn ($product) => [
                        'id' => $product->id,
                        'label' => $product->sku.' · '.$product->name,
                        'unit_id' => $product->base_unit_id,
                        'unit_symbol' => $product->baseUnit->symbol,
                        'track_batch' => $product->track_batch,
                        'track_expiry' => $product->track_expiry,
                    ])->values()) }}, {{ Js::from(old('items', [])) }})"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.select name="warehouse_id" label="Gudang tujuan" required>
                            <option value="">Pilih gudang</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->code }} · {{ $warehouse->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input name="occurred_at" type="datetime-local" label="Tanggal saldo awal" :value="old('occurred_at', now()->timezone(config('retail.display_timezone'))->format('Y-m-d\TH:i'))" required />
                    </div>
                    <x-ui.textarea name="reason" label="Alasan dan sumber data" :value="old('reason')" rows="3" required />

                    <div class="grid gap-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><h3 class="font-semibold text-slate-950">Baris barang</h3><p class="text-sm text-slate-500">Kuantitas otomatis dicatat dalam satuan dasar.</p></div>
                            <x-ui.button type="button" variant="secondary" x-on:click="addItem()">Tambah baris</x-ui.button>
                        </div>

                        <template x-for="(item, index) in items" :key="item.key">
                            <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                                <div class="grid gap-1.5">
                                    <label class="text-sm font-semibold text-slate-700">Barang <span class="text-danger-600">*</span></label>
                                    <select x-model="item.product_id" x-on:change="selectProduct(item)" x-bind:name="`items[${index}][product_id]`" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2" required>
                                        <option value="">Pilih barang</option>
                                        <template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.label"></option></template>
                                    </select>
                                    <input type="hidden" x-bind:name="`items[${index}][unit_id]`" x-model="item.unit_id">
                                    <input type="hidden" x-bind:name="`items[${index}][stock_status]`" value="sellable">
                                    <span class="text-xs text-slate-500" x-text="item.unit_symbol ? `Satuan dasar: ${item.unit_symbol}` : 'Pilih barang terlebih dahulu'"></span>
                                </div>
                                <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`quantity-${item.key}`">Kuantitas <span class="text-danger-600">*</span></label><input type="number" min="0.001" step="0.001" x-bind:id="`quantity-${item.key}`" x-bind:name="`items[${index}][quantity]`" x-model="item.quantity" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2" required></div>
                                <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`unit-cost-${item.key}`">HPP satuan <span class="text-danger-600">*</span></label><input type="number" min="0" step="0.000001" x-bind:id="`unit-cost-${item.key}`" x-bind:name="`items[${index}][unit_cost]`" x-model="item.unit_cost" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2" required></div>
                                <div class="grid gap-2">
                                    <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`lot-${item.key}`">Nomor batch</label><input type="text" x-bind:id="`lot-${item.key}`" x-bind:name="`items[${index}][lot_number]`" x-model="item.lot_number" x-bind:required="item.track_batch" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2"></div>
                                    <div class="grid gap-1.5"><label class="text-sm font-semibold text-slate-700" x-bind:for="`expiry-${item.key}`">Kedaluwarsa</label><input type="date" x-bind:id="`expiry-${item.key}`" x-bind:name="`items[${index}][expires_at]`" x-model="item.expires_at" x-bind:required="item.track_expiry" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2"></div>
                                </div>
                                <div class="flex items-end"><x-ui.button type="button" variant="ghost" x-on:click="removeItem(index)" x-bind:disabled="items.length === 1">Hapus</x-ui.button></div>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end"><x-ui.button type="submit" x-bind:disabled="submitting"><span x-show="! submitting">Ajukan persetujuan</span><span x-show="submitting" x-cloak>Menyimpan…</span></x-ui.button></div>
                </form>
            </x-ui.card>
        @else
            <x-ui.alert variant="info" title="Akses terbatas">Anda dapat melihat dokumen, tetapi tidak memiliki izin mengajukan saldo awal.</x-ui.alert>
        @endcan

        <x-ui.card title="Dokumen saldo awal" description="Status persetujuan dan posting pada cabang aktif.">
            @if ($adjustments->isEmpty())
                <div class="py-10 text-center text-sm text-slate-500">Belum ada dokumen saldo awal.</div>
            @else
                <div class="grid gap-3">
                    @foreach ($adjustments as $adjustment)
                        <article class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-[1fr_auto] md:items-center">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950">{{ $adjustment->document_number }}</h3><x-ui.badge>{{ str($adjustment->status->value)->replace('_', ' ')->title() }}</x-ui.badge></div><p class="mt-1 text-sm text-slate-500">{{ $adjustment->destinationWarehouse->name }} · {{ $adjustment->creator->name }} · {{ $adjustment->occurred_at->timezone(config('retail.display_timezone'))->format('d/m/Y H:i') }}</p><p class="mt-1 text-sm text-slate-700">{{ $adjustment->reason }}</p></div>
                            @can('approve', $adjustment)
                                @if ($adjustment->status === \App\Domain\Inventory\Enums\StockAdjustmentStatus::PendingApproval && $adjustment->created_by !== auth()->id())
                                    <form method="POST" action="{{ route('inventory.opening.approve', $adjustment) }}" class="grid gap-2 sm:grid-cols-[16rem_auto]" x-data="{ submitting: false }" x-on:submit="submitting = true">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <x-ui.input name="decision_notes" :id="'decision-notes-'.$adjustment->public_id" label="Catatan persetujuan" required />
                                        <div class="flex items-end"><x-ui.button type="submit" x-bind:disabled="submitting">Setujui & posting</x-ui.button></div>
                                    </form>
                                @endif
                            @endcan
                        </article>
                    @endforeach
                </div>
                <div class="mt-5">{{ $adjustments->links() }}</div>
            @endif
        </x-ui.card>
    </div>

    @pushOnce('scripts')
        <script>
            function openingBalanceForm(products, initialItems) {
                const blankItem = () => ({ key: crypto.randomUUID(), product_id: '', unit_id: '', unit_symbol: '', quantity: '', unit_cost: '', lot_number: '', expires_at: '', track_batch: false, track_expiry: false });
                const hydratedItems = initialItems.length > 0
                    ? initialItems.map(initial => {
                        const product = products.find(candidate => String(candidate.id) === String(initial.product_id));
                        return { ...blankItem(), ...initial, unit_id: product?.unit_id ?? initial.unit_id ?? '', unit_symbol: product?.unit_symbol ?? '', track_batch: product?.track_batch ?? false, track_expiry: product?.track_expiry ?? false };
                    })
                    : [blankItem()];
                return {
                    products,
                    items: hydratedItems,
                    submitting: false,
                    addItem() { this.items.push(blankItem()); },
                    removeItem(index) { if (this.items.length > 1) this.items.splice(index, 1); },
                    selectProduct(item) {
                        const product = this.products.find(candidate => String(candidate.id) === String(item.product_id));
                        item.unit_id = product?.unit_id ?? '';
                        item.unit_symbol = product?.unit_symbol ?? '';
                        item.track_batch = product?.track_batch ?? false;
                        item.track_expiry = product?.track_expiry ?? false;
                    },
                };
            }
        </script>
    @endPushOnce
</x-layouts.app>
