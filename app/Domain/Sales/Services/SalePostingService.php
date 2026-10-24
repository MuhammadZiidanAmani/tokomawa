<?php

namespace App\Domain\Sales\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Enums\CashMovementType;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\SaleStatus;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Shared\Services\DocumentNumberService;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class SalePostingService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly DocumentNumberService $documentNumbers,
        private readonly SalesPricingService $pricing,
        private readonly InventoryPostingService $inventoryPosting,
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $data */
    public function checkout(User $actor, Branch $branch, array $data): Sale
    {
        if (! $actor->is_active || ! $actor->hasPermission('sales.checkout') || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        $result = $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'sale.checkout',
            $data['idempotency_key'],
            $data,
            function () use ($actor, $branch, $data): array {
                $shift = CashShift::query()->whereKey((int) $data['cash_shift_id'])->lockForUpdate()->firstOrFail();
                $this->validateShift($shift, $actor, $branch);
                $register = Register::query()->whereKey($shift->register_id)->lockForUpdate()->firstOrFail();
                $warehouse = Warehouse::query()->whereKey($shift->warehouse_id)->lockForUpdate()->firstOrFail();
                if (! $register->is_active || ! $warehouse->is_active) {
                    throw new DomainException('Register atau gudang shift sudah tidak aktif.');
                }

                $soldAt = CarbonImmutable::now('UTC');
                $quote = $this->pricing->quoteCart($branch, $warehouse, $data['items'], $soldAt);
                $tendered = bcadd((string) $data['cash_tendered'], '0', 2);
                if (bccomp($tendered, $quote['grand_total'], 2) < 0) {
                    throw new DomainException('Uang diterima kurang dari total belanja.');
                }
                $changeDue = bcsub($tendered, $quote['grand_total'], 2);
                $sale = Sale::query()->create([
                    'branch_id' => $branch->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'register_id' => $register->getKey(),
                    'cash_shift_id' => $shift->getKey(),
                    'cashier_id' => $actor->getKey(),
                    'price_list_id' => $quote['price_list_id'],
                    'document_number' => $this->documentNumbers->next($branch, 'sale', 'SALE', $soldAt),
                    'status' => SaleStatus::Posting,
                    'sold_at' => $soldAt,
                    'currency' => 'IDR',
                    'subtotal' => $quote['subtotal'],
                    'discount_total' => $quote['discount_total'],
                    'tax_total' => $quote['tax_total'],
                    'grand_total' => $quote['grand_total'],
                    'cash_tendered' => $tendered,
                    'change_due' => $changeDue,
                    'posting_key' => $data['idempotency_key'],
                    'posted_by' => $actor->getKey(),
                ]);

                foreach ($quote['items'] as $index => $quotedItem) {
                    $linePublicId = (string) Str::ulid();
                    $movement = $this->inventoryPosting->issueSale(
                        $branch,
                        $warehouse,
                        $quotedItem['product'],
                        $quotedItem['batch'],
                        $quotedItem['base_quantity'],
                        $sale->public_id,
                        $linePublicId,
                        $soldAt,
                        $actor,
                        $data['idempotency_key'],
                    );
                    SaleItem::query()->create([
                        'public_id' => $linePublicId,
                        'sale_id' => $sale->getKey(),
                        'line_number' => $index + 1,
                        ...collect($quotedItem)->except(['product', 'unit', 'batch', 'price_list_id'])->all(),
                        'cogs_unit_snapshot' => $movement->unit_cost_snapshot,
                        'cogs_total_snapshot' => bcmul($quotedItem['base_quantity'], $movement->unit_cost_snapshot, 6),
                        'inventory_movement_id' => $movement->getKey(),
                    ]);
                }

                $payment = SalePayment::query()->create([
                    'sale_id' => $sale->getKey(),
                    'branch_id' => $branch->getKey(),
                    'cash_shift_id' => $shift->getKey(),
                    'method' => PaymentMethod::Cash,
                    'status' => 'settled',
                    'amount' => $quote['grand_total'],
                    'tendered_amount' => $tendered,
                    'change_amount' => $changeDue,
                    'source_key' => 'sale:'.$sale->public_id.':cash',
                    'paid_at' => $soldAt,
                    'created_by' => $actor->getKey(),
                ]);
                CashMovement::query()->create([
                    'branch_id' => $branch->getKey(),
                    'register_id' => $register->getKey(),
                    'cash_shift_id' => $shift->getKey(),
                    'sale_id' => $sale->getKey(),
                    'sale_payment_id' => $payment->getKey(),
                    'movement_type' => CashMovementType::Sale,
                    'amount_delta' => $quote['grand_total'],
                    'source_key' => 'sale:'.$sale->public_id.':cash-movement',
                    'description' => 'Penerimaan tunai penjualan '.$sale->document_number,
                    'occurred_at' => $soldAt,
                    'created_by' => $actor->getKey(),
                ]);
                $sale->update([
                    'status' => SaleStatus::Posted,
                    'posted_at' => now(),
                    'version' => $sale->version + 1,
                ]);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Checkout tunai POS.'),
                    'sale.posted',
                    'sale',
                    $sale->public_id,
                    after: [
                        'document_number' => $sale->document_number,
                        'cash_shift_id' => $shift->public_id,
                        'grand_total' => $quote['grand_total'],
                        'cash_tendered' => $tendered,
                        'change_due' => $changeDue,
                    ],
                );
                $this->outbox->record($branch, 'sale.posted', 'sale', $sale->public_id, [
                    'document_number' => $sale->document_number,
                    'cash_shift_id' => $shift->public_id,
                    'grand_total' => $quote['grand_total'],
                ]);

                return [
                    'sale_id' => $sale->public_id,
                    'document_number' => $sale->document_number,
                    'grand_total' => $quote['grand_total'],
                    'cash_tendered' => $tendered,
                    'change_due' => $changeDue,
                ];
            },
            $actor,
            201,
        );

        return Sale::query()->where('public_id', $result->payload['sale_id'])->firstOrFail();
    }

    private function validateShift(CashShift $shift, User $actor, Branch $branch): void
    {
        if ($shift->branch_id !== $branch->getKey()) {
            throw new AuthorizationException;
        }
        if ($shift->status !== CashShiftStatus::Open || $shift->active_register_key === null) {
            throw new DomainException('Shift kasir tidak aktif.');
        }
        if ($shift->cashier_id !== $actor->getKey() && ! $actor->hasPermission('cash_shifts.use_any')) {
            throw new AuthorizationException;
        }
    }
}
