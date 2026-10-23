<x-layouts.app title="Rekonsiliasi Persediaan">
    <div class="grid gap-6">
        <x-page-header title="Rekonsiliasi Persediaan" description="Perbandingan read-only antara jumlah ledger immutable dan proyeksi saldo." />

        @php($differences = $results->where('is_reconciled', false))
        @if ($differences->isEmpty())
            <x-ui.alert variant="success" title="Saldo sesuai">Seluruh kombinasi barang, gudang, batch, dan status cocok dengan ledger.</x-ui.alert>
        @else
            <x-ui.alert variant="danger" title="Ditemukan perbedaan">{{ $differences->count() }} saldo memerlukan investigasi. Rekonsiliasi ini tidak mengubah data secara otomatis.</x-ui.alert>
        @endif

        <x-ui.card>
            @if ($results->isEmpty())
                <div class="py-10 text-center text-sm text-slate-500">Belum ada data persediaan untuk direkonsiliasi.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500"><tr><th class="px-3 py-2">Gudang</th><th class="px-3 py-2">Barang</th><th class="px-3 py-2">Batch/Status</th><th class="px-3 py-2 text-right">Saldo</th><th class="px-3 py-2 text-right">Ledger</th><th class="px-3 py-2 text-right">Selisih qty</th><th class="px-3 py-2 text-right">Selisih nilai</th><th class="px-3 py-2">Hasil</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($results as $row)
                                <tr @class(['bg-red-50' => ! $row['is_reconciled']])>
                                    <td class="px-3 py-3">{{ $row['warehouse_name'] }}</td>
                                    <td class="px-3 py-3"><span class="font-semibold">{{ $row['product_sku'] }}</span><br>{{ $row['product_name'] }}</td>
                                    <td class="px-3 py-3">{{ $row['batch_scope'] }}<br><span class="text-xs text-slate-500">{{ $row['stock_status'] }}</span></td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['balance_quantity'] }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ $row['ledger_quantity'] }}</td>
                                    <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $row['quantity_difference'] }}</td>
                                    <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $row['value_difference'] }}</td>
                                    <td class="px-3 py-3"><x-ui.badge :variant="$row['is_reconciled'] ? 'success' : 'danger'">{{ $row['is_reconciled'] ? 'Sesuai' : 'Berbeda' }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
