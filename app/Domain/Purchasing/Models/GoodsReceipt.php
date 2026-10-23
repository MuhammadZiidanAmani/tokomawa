<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Database\Factories\GoodsReceiptFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'branch_id', 'warehouse_id', 'purchase_order_id', 'supplier_id', 'document_number', 'status',
    'supplier_delivery_note', 'supplier_delivery_note_normalized', 'received_at', 'supplier_code_snapshot',
    'supplier_name_snapshot', 'total_base_quantity', 'total_value', 'notes', 'creation_key', 'posting_key',
    'version', 'created_by', 'posted_by', 'cancelled_by', 'posted_at', 'cancelled_at', 'cancellation_reason',
])]
class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (GoodsReceipt $receipt): void {
            $receipt->public_id ??= (string) Str::ulid();
        });
        static::updating(function (GoodsReceipt $receipt): void {
            if ($receipt->getRawOriginal('status') === GoodsReceiptStatus::Posted->value) {
                throw new DomainException('Penerimaan yang sudah diposting tidak dapat diubah.');
            }
        });
        static::deleting(fn (): never => throw new DomainException('Penerimaan barang tidak dapat dihapus. Batalkan draft atau buat koreksi terotorisasi.'));
    }

    protected static function newFactory(): GoodsReceiptFactory
    {
        return GoodsReceiptFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'datetime',
            'total_base_quantity' => 'decimal:3',
            'total_value' => 'decimal:2',
            'version' => 'integer',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
