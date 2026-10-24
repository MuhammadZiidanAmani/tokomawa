<?php

namespace App\Domain\AccountsPayable\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Enums\PurchaseType;
use App\Domain\AccountsPayable\Models\ApEntry;
use App\Domain\AccountsPayable\Models\GoodsReceiptInvoiceAllocation;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\AccountsPayable\Models\PurchaseInvoiceCashPayment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Shared\Services\DocumentNumberService;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class PurchaseInvoiceService
{
    public function __construct(private readonly DocumentNumberService $numbers, private readonly IdempotencyService $idempotency, private readonly AuditLogger $audit, private readonly OutboxRecorder $outbox) {}

    /** @param array<string,mixed> $data */
    public function createDraft(User $actor, Branch $branch, array $data): PurchaseInvoice
    {
        $this->authorize($actor, $branch, 'purchase_invoices.create');
        $result = $this->idempotency->execute('branch:'.$branch->id, 'purchase_invoice.create', $data['idempotency_key'], $data, function () use ($actor, $branch, $data): array {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail((int) $data['supplier_id']);
            if (! $supplier->is_active) {
                throw new DomainException('Supplier sudah tidak aktif.');
            }
            $normalized = $this->normalize((string) $data['supplier_invoice_number']);
            if (PurchaseInvoice::query()->where('supplier_id', $supplier->id)->where('supplier_invoice_number_normalized', $normalized)->exists()) {
                throw new DomainException('Nomor faktur supplier sudah pernah digunakan.');
            }
            [$items, $allocations, $totals] = $this->buildLines($branch, $supplier, $data['items']);
            $invoice = PurchaseInvoice::query()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'document_number' => $this->numbers->next($branch, 'purchase_invoice', 'PI', CarbonImmutable::parse($data['invoice_date'])), 'supplier_invoice_number' => trim((string) $data['supplier_invoice_number']), 'supplier_invoice_number_normalized' => $normalized, 'status' => PurchaseInvoiceStatus::Draft, 'purchase_type' => $data['purchase_type'], 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'] ?? null, 'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name, 'supplier_address_snapshot' => $supplier->address, ...$totals, 'notes' => $data['notes'] ?? null, 'creation_key' => $data['idempotency_key'], 'created_by' => $actor->id]);
            foreach ($items as $index => $item) {
                $created = $invoice->items()->create($item);
                $invoice->allocations()->create([...$allocations[$index], 'purchase_invoice_item_id' => $created->id]);
            }
            $this->audit->record(new AuditContext($actor, $branch, 'Membuat draft faktur pembelian.'), 'purchase_invoice.created', 'purchase_invoice', $invoice->public_id, after: $this->snapshot($invoice));

            return ['purchase_invoice_id' => $invoice->public_id];
        }, $actor, 201);

        return PurchaseInvoice::query()->where('public_id', $result->payload['purchase_invoice_id'])->firstOrFail();
    }

    /** @param array<string,mixed> $data */
    public function updateDraft(PurchaseInvoice $invoice, User $actor, Branch $branch, array $data): PurchaseInvoice
    {
        $this->authorize($actor, $branch, 'purchase_invoices.update');
        $this->ensureBranch($invoice, $branch);
        $result = $this->idempotency->execute('purchase_invoice:'.$invoice->public_id, 'purchase_invoice.update', $data['idempotency_key'], $data, function () use ($invoice, $actor, $branch, $data): array {
            $locked = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== PurchaseInvoiceStatus::Draft) {
                throw new DomainException('Hanya faktur draft yang dapat diubah.');
            }
            $supplier = Supplier::query()->lockForUpdate()->findOrFail((int) $data['supplier_id']);
            $normalized = $this->normalize((string) $data['supplier_invoice_number']);
            if (PurchaseInvoice::query()->where('supplier_id', $supplier->id)->where('supplier_invoice_number_normalized', $normalized)->whereKeyNot($locked->id)->exists()) {
                throw new DomainException('Nomor faktur supplier sudah pernah digunakan.');
            }
            [$items,$allocations,$totals] = $this->buildLines($branch, $supplier, $data['items']);
            $before = $this->snapshot($locked);
            $locked->update(['supplier_id' => $supplier->id, 'supplier_invoice_number' => trim((string) $data['supplier_invoice_number']), 'supplier_invoice_number_normalized' => $normalized, 'purchase_type' => $data['purchase_type'], 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'] ?? null, 'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name, 'supplier_address_snapshot' => $supplier->address, ...$totals, 'notes' => $data['notes'] ?? null, 'version' => $locked->version + 1]);
            $locked->allocations()->delete();
            $locked->items()->delete();
            foreach ($items as $index => $item) {
                $created = $locked->items()->create($item);
                $locked->allocations()->create([...$allocations[$index], 'purchase_invoice_item_id' => $created->id]);
            }
            $this->audit->record(new AuditContext($actor, $branch, 'Memperbarui draft faktur pembelian.'), 'purchase_invoice.updated', 'purchase_invoice', $locked->public_id, before: $before, after: $this->snapshot($locked));

            return ['purchase_invoice_id' => $locked->public_id];
        }, $actor);

        return PurchaseInvoice::query()->where('public_id', $result->payload['purchase_invoice_id'])->firstOrFail();
    }

    public function post(PurchaseInvoice $invoice, User $actor, Branch $branch, string $key): PurchaseInvoice
    {
        $this->authorize($actor, $branch, 'purchase_invoices.post');
        $this->ensureBranch($invoice, $branch);
        $result = $this->idempotency->execute('purchase_invoice:'.$invoice->public_id, 'purchase_invoice.post', $key, ['purchase_invoice_id' => $invoice->public_id], function () use ($invoice, $actor, $branch, $key): array {
            $locked = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== PurchaseInvoiceStatus::Draft) {
                throw new DomainException('Faktur sudah diposting.');
            }
            if (bccomp($locked->tax_total, '0', 2) > 0 || $locked->items()->whereNotNull('tax_rule_id')->exists()) {
                throw new DomainException('Posting faktur berpajak ditahan sampai DEC-006 disahkan.');
            }
            if ($locked->purchase_type === PurchaseType::Credit && $locked->due_date === null) {
                throw new DomainException('Tanggal jatuh tempo wajib untuk faktur kredit.');
            }
            $allocations = GoodsReceiptInvoiceAllocation::query()->where('purchase_invoice_id', $locked->id)->orderBy('goods_receipt_item_id')->lockForUpdate()->get();
            $receiptItems = GoodsReceiptItem::query()->whereIn('id', $allocations->pluck('goods_receipt_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($allocations as $allocation) {
                $receiptItem = $receiptItems->get($allocation->goods_receipt_item_id);
                if ($receiptItem === null || ! $receiptItem->goodsReceipt()->where('status', GoodsReceiptStatus::Posted->value)->where('branch_id', $branch->id)->where('supplier_id', $locked->supplier_id)->exists()) {
                    throw new DomainException('Referensi penerimaan tidak sah.');
                }
                $already = GoodsReceiptInvoiceAllocation::query()
                    ->join('purchase_invoices', 'purchase_invoices.id', '=', 'goods_receipt_invoice_allocations.purchase_invoice_id')
                    ->where('goods_receipt_item_id', $receiptItem->id)
                    ->where('purchase_invoices.status', PurchaseInvoiceStatus::Posted->value)
                    ->orderBy('goods_receipt_invoice_allocations.id')
                    ->lockForUpdate()
                    ->pluck('allocated_base_quantity')
                    ->reduce(fn (string $total, mixed $quantity): string => bcadd($total, (string) $quantity, 3), '0.000');
                if (bccomp(bcadd($already, $allocation->allocated_base_quantity, 3), $receiptItem->base_quantity, 3) > 0) {
                    throw new DomainException('Kuantitas faktur melebihi penerimaan yang belum ditagihkan.');
                }
            }
            ApEntry::query()->create(['branch_id' => $branch->id, 'supplier_id' => $locked->supplier_id, 'purchase_invoice_id' => $locked->id, 'entry_type' => 'invoice_charge', 'amount_delta' => $locked->grand_total, 'due_date' => $locked->due_date, 'source_key' => 'purchase_invoice:'.$locked->public_id.':charge', 'occurred_at' => now(), 'description' => 'Faktur '.$locked->supplier_invoice_number, 'created_by' => $actor->id]);
            $outstanding = $locked->grand_total;
            if ($locked->purchase_type === PurchaseType::Cash) {
                $payment = PurchaseInvoiceCashPayment::query()->create(['purchase_invoice_id' => $locked->id, 'branch_id' => $branch->id, 'supplier_id' => $locked->supplier_id, 'payment_number' => $this->numbers->next($branch, 'purchase_invoice_cash_payment', 'CP', now()), 'amount' => $locked->grand_total, 'paid_at' => now(), 'reference' => $locked->supplier_invoice_number, 'created_by' => $actor->id]);
                ApEntry::query()->create(['branch_id' => $branch->id, 'supplier_id' => $locked->supplier_id, 'purchase_invoice_id' => $locked->id, 'cash_payment_id' => $payment->id, 'entry_type' => 'cash_settlement', 'amount_delta' => bcsub('0', $locked->grand_total, 2), 'source_key' => 'purchase_invoice:'.$locked->public_id.':cash', 'occurred_at' => now(), 'description' => 'Pelunasan tunai '.$payment->payment_number, 'created_by' => $actor->id]);
                $outstanding = '0.00';
            }
            $locked->update(['status' => PurchaseInvoiceStatus::Posted, 'outstanding_amount' => $outstanding, 'posting_key' => $key, 'posted_by' => $actor->id, 'posted_at' => now(), 'version' => $locked->version + 1]);
            $this->audit->record(new AuditContext($actor, $branch, 'Memposting faktur tanpa mutasi stok.'), 'purchase_invoice.posted', 'purchase_invoice', $locked->public_id, before: ['status' => 'draft'], after: ['status' => 'posted', 'outstanding_amount' => $outstanding]);
            $this->outbox->record($branch, 'purchase_invoice.posted', 'purchase_invoice', $locked->public_id, ['document_number' => $locked->document_number, 'grand_total' => $locked->grand_total]);

            return ['purchase_invoice_id' => $locked->public_id];
        }, $actor);

        return PurchaseInvoice::query()->where('public_id', $result->payload['purchase_invoice_id'])->firstOrFail();
    }

    /** @param list<array<string,mixed>> $input @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:array<string,string>} */
    private function buildLines(Branch $branch, Supplier $supplier, array $input): array
    {
        $ids = collect($input)->pluck('goods_receipt_item_id')->map(fn ($id): int => (int) $id);
        if ($ids->unique()->count() !== $ids->count()) {
            throw new DomainException('Item penerimaan tidak boleh diduplikasi.');
        }
        $receiptItems = GoodsReceiptItem::query()->with('goodsReceipt')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($receiptItems->count() !== $ids->count()) {
            throw new DomainException('Item penerimaan tidak ditemukan.');
        }
        $items = [];
        $allocations = [];
        $subtotal = '0.00';
        $discount = '0.00';
        $tax = '0.00';
        $grand = '0.00';
        foreach ($input as $index => $line) {
            $receipt = $receiptItems->get((int) $line['goods_receipt_item_id']);
            if ($receipt->goodsReceipt->status !== GoodsReceiptStatus::Posted || $receipt->goodsReceipt->branch_id !== $branch->id || $receipt->goodsReceipt->supplier_id !== $supplier->id) {
                throw new DomainException('Item penerimaan tidak cocok dengan cabang atau supplier.');
            }
            $base = bcadd((string) $line['base_quantity'], '0', 3);
            $quantity = bcdiv($base, $receipt->conversion_factor, 3);
            $price = bcadd((string) $line['invoice_unit_price'], '0', 2);
            $lineSubtotal = bcround(bcmul($quantity, $price, 5), 2);
            $lineDiscount = bcadd((string) ($line['discount_amount'] ?? '0'), '0', 2);
            $lineTax = bcadd((string) ($line['tax_amount'] ?? '0'), '0', 2);
            if (bccomp($base, '0', 3) <= 0 || bccomp($lineDiscount, $lineSubtotal, 2) > 0) {
                throw new DomainException('Kuantitas atau diskon item tidak valid.');
            }
            $lineTotal = bcadd(bcsub($lineSubtotal, $lineDiscount, 2), $lineTax, 2);
            $taxRule = empty($line['tax_rule_id']) ? null : TaxRule::query()->findOrFail((int) $line['tax_rule_id']);
            $items[] = ['line_number' => $index + 1, 'product_id' => $receipt->product_id, 'unit_id' => $receipt->unit_id, 'product_sku_snapshot' => $receipt->product_sku_snapshot, 'product_name_snapshot' => $receipt->product_name_snapshot, 'unit_code_snapshot' => $receipt->unit_code_snapshot, 'unit_name_snapshot' => $receipt->unit_name_snapshot, 'unit_symbol_snapshot' => $receipt->unit_symbol_snapshot, 'conversion_factor' => $receipt->conversion_factor, 'quantity' => $quantity, 'base_quantity' => $base, 'receipt_unit_price_snapshot' => $receipt->unit_price_snapshot, 'invoice_unit_price' => $price, 'price_variance' => bcround(bcmul($quantity, bcsub($price, $receipt->unit_price_snapshot, 2), 5), 2), 'subtotal' => $lineSubtotal, 'discount_amount' => $lineDiscount, 'tax_rule_id' => $taxRule?->id, 'tax_code_snapshot' => $taxRule?->code, 'tax_rate_snapshot' => $taxRule?->rate, 'tax_mode_snapshot' => $taxRule?->calculation_mode, 'tax_rounding_snapshot' => $taxRule?->rounding_mode?->value, 'tax_amount' => $lineTax, 'line_total' => $lineTotal];
            $allocations[] = ['goods_receipt_id' => $receipt->goods_receipt_id, 'goods_receipt_item_id' => $receipt->id, 'allocated_base_quantity' => $base];
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $discount = bcadd($discount, $lineDiscount, 2);
            $tax = bcadd($tax, $lineTax, 2);
            $grand = bcadd($grand, $lineTotal, 2);
        }

        return [$items, $allocations, ['subtotal' => $subtotal, 'discount_total' => $discount, 'tax_total' => $tax, 'grand_total' => $grand]];
    }

    private function normalize(string $number): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $number));
    }

    private function authorize(User $actor, Branch $branch, string $permission): void
    {
        if (! $actor->hasPermission($permission) || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }
    }

    private function ensureBranch(PurchaseInvoice $invoice, Branch $branch): void
    {
        if ($invoice->branch_id !== $branch->id) {
            throw new AuthorizationException;
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(PurchaseInvoice $invoice): array
    {
        return $invoice->only(['public_id', 'document_number', 'supplier_id', 'supplier_invoice_number', 'status', 'purchase_type', 'invoice_date', 'due_date', 'grand_total', 'outstanding_amount', 'version']);
    }
}
