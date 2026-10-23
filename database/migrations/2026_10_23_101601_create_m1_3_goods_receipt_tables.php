<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 40);
            $table->string('status', 32)->default('draft');
            $table->string('supplier_delivery_note', 100);
            $table->string('supplier_delivery_note_normalized', 100);
            $table->timestampTz('received_at');
            $table->string('supplier_code_snapshot', 40);
            $table->string('supplier_name_snapshot');
            $table->decimal('total_base_quantity', 24, 3)->unsigned()->default(0);
            $table->decimal('total_value', 24, 2)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->uuid('creation_key')->unique();
            $table->uuid('posting_key')->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'document_number'], 'goods_receipts_branch_document_unique');
            $table->unique(['supplier_id', 'supplier_delivery_note_normalized'], 'goods_receipts_supplier_delivery_note_unique');
            $table->index(['branch_id', 'status', 'received_at'], 'goods_receipts_branch_status_date_index');
            $table->index(['purchase_order_id', 'status'], 'goods_receipts_purchase_order_status_index');
            $table->index(['warehouse_id', 'received_at'], 'goods_receipts_warehouse_date_index');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_sku_snapshot', 64);
            $table->string('product_name_snapshot');
            $table->string('unit_code_snapshot', 32);
            $table->string('unit_name_snapshot', 100);
            $table->string('unit_symbol_snapshot', 24);
            $table->decimal('conversion_factor', 18, 6)->unsigned();
            $table->decimal('quantity', 18, 3)->unsigned();
            $table->decimal('base_quantity', 18, 3)->unsigned();
            $table->decimal('unit_price_snapshot', 18, 2)->unsigned();
            $table->decimal('base_unit_cost_snapshot', 18, 6)->unsigned();
            $table->decimal('line_total', 24, 2)->unsigned();
            $table->boolean('track_batch_snapshot')->default(false);
            $table->boolean('track_expiry_snapshot')->default(false);
            $table->string('lot_number_snapshot', 100)->nullable();
            $table->date('manufactured_at_snapshot')->nullable();
            $table->date('expires_at_snapshot')->nullable();
            $table->string('stock_status', 32)->default('sellable');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'line_number'], 'goods_receipt_items_line_unique');
            $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'goods_receipt_items_po_line_unique');
            $table->index(['purchase_order_item_id', 'goods_receipt_id'], 'goods_receipt_items_po_item_index');
            $table->index(['product_id', 'inventory_batch_id'], 'goods_receipt_items_product_batch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
