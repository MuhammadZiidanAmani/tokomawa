<x-layouts.app title="Purchase Order">
    <div class="grid gap-6">
        <x-page-header title="Purchase Order" description="Kelola pesanan supplier tanpa mengubah stok atau membentuk hutang.">
            <x-slot:actions>
                @can('create', App\Domain\Purchasing\Models\PurchaseOrder::class)
                    <x-ui.button :href="route('purchasing.purchase-orders.create')">Buat Purchase Order</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card title="Pencarian dan filter" description="Hasil dibatasi 20 dokumen per halaman.">
            <form method="GET" action="{{ route('purchasing.purchase-orders.index') }}" class="grid gap-4 lg:grid-cols-4">
                <x-ui.input name="search" label="Nomor PO atau supplier" :value="request('search')" placeholder="Cari Purchase Order..." />
                <x-ui.select name="supplier_id" label="Supplier">
                    <option value="">Semua supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="status" label="Status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit">Terapkan</x-ui.button>
                    <x-ui.button :href="route('purchasing.purchase-orders.index')" variant="secondary">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($purchaseOrders->isEmpty())
            <x-ui.card>
                <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
                    <p class="font-semibold text-slate-800">Belum ada Purchase Order yang sesuai.</p>
                    <p class="mt-1 text-sm text-slate-500">Buat draft baru atau ubah filter pencarian.</p>
                </div>
            </x-ui.card>
        @else
            <x-ui.data-table label="Daftar Purchase Order">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor / Tanggal</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchaseOrders as $purchaseOrder)
                        @php
                            $variant = match ($purchaseOrder->status) {
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::Approved,
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::Completed => 'success',
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::PendingApproval,
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::Sent,
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::PartiallyReceived => 'warning',
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::Rejected,
                                App\Domain\Purchasing\Enums\PurchaseOrderStatus::Cancelled => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3"><p class="font-mono text-xs font-semibold text-slate-950">{{ $purchaseOrder->document_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $purchaseOrder->order_date->translatedFormat('d M Y') }}</p></td>
                            <td class="px-4 py-3"><p class="font-semibold text-slate-900">{{ $purchaseOrder->supplier_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $purchaseOrder->supplier_code_snapshot }}</p></td>
                            <td class="px-4 py-3 text-slate-600">{{ $purchaseOrder->items_count }} item</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ app(App\Domain\Shared\Support\IndonesiaFormatter::class)->currencyDecimal($purchaseOrder->grand_total) }}</td>
                            <td class="px-4 py-3"><x-ui.badge :variant="$variant">{{ $purchaseOrder->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('purchasing.purchase-orders.show', $purchaseOrder) }}" class="font-semibold text-primary-700 hover:underline">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.data-table>
            <div>{{ $purchaseOrders->links() }}</div>
        @endif
    </div>
</x-layouts.app>
