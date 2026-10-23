<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 40);
            $table->string('supplier_invoice_number', 100);
            $table->string('supplier_invoice_number_normalized', 100);
            $table->string('status', 24)->default('draft');
            $table->string('purchase_type', 16);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->string('supplier_code_snapshot', 40);
            $table->string('supplier_name_snapshot');
            $table->text('supplier_address_snapshot')->nullable();
            $table->decimal('subtotal', 24, 2)->unsigned()->default(0);
            $table->decimal('discount_total', 24, 2)->unsigned()->default(0);
            $table->decimal('tax_total', 24, 2)->unsigned()->default(0);
            $table->decimal('grand_total', 24, 2)->unsigned()->default(0);
            $table->decimal('outstanding_amount', 24, 2)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->uuid('creation_key')->unique();
            $table->uuid('posting_key')->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'document_number'], 'purchase_invoices_branch_document_unique');
            $table->unique(['supplier_id', 'supplier_invoice_number_normalized'], 'purchase_invoices_supplier_number_unique');
            $table->index(['branch_id', 'status', 'invoice_date'], 'purchase_invoices_branch_status_date_index');
            $table->index(['supplier_id', 'status', 'due_date'], 'purchase_invoices_supplier_due_index');
        });
        Schema::create('purchase_invoice_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('purchase_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('product_sku_snapshot', 64);
            $table->string('product_name_snapshot');
            $table->string('unit_code_snapshot', 32);
            $table->string('unit_name_snapshot', 100);
            $table->string('unit_symbol_snapshot', 24);
            $table->decimal('conversion_factor', 18, 6)->unsigned();
            $table->decimal('quantity', 18, 3)->unsigned();
            $table->decimal('base_quantity', 18, 3)->unsigned();
            $table->decimal('receipt_unit_price_snapshot', 18, 2)->unsigned();
            $table->decimal('invoice_unit_price', 18, 2)->unsigned();
            $table->decimal('price_variance', 24, 2)->default(0);
            $table->decimal('subtotal', 24, 2)->unsigned();
            $table->decimal('discount_amount', 24, 2)->unsigned()->default(0);
            $table->foreignId('tax_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tax_code_snapshot', 64)->nullable();
            $table->decimal('tax_rate_snapshot', 7, 4)->nullable();
            $table->string('tax_mode_snapshot', 20)->nullable();
            $table->string('tax_rounding_snapshot', 32)->nullable();
            $table->decimal('tax_amount', 24, 2)->unsigned()->default(0);
            $table->decimal('line_total', 24, 2)->unsigned();
            $table->timestamps();
            $table->unique(['purchase_invoice_id', 'line_number'], 'purchase_invoice_items_line_unique');
            $table->index(['product_id', 'purchase_invoice_id'], 'purchase_invoice_items_product_index');
        });
        Schema::create('goods_receipt_invoice_allocations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('purchase_invoice_item_id');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('goods_receipt_item_id');
            $table->decimal('allocated_base_quantity', 18, 3)->unsigned();
            $table->timestamps();
            $table->unique(['purchase_invoice_item_id', 'goods_receipt_item_id'], 'receipt_invoice_allocation_line_unique');
            $table->index(['goods_receipt_item_id', 'purchase_invoice_id'], 'receipt_invoice_allocation_outstanding_index');
            $table->foreign('purchase_invoice_id', 'gr_invoice_alloc_invoice_fk')->references('id')->on('purchase_invoices')->restrictOnDelete();
            $table->foreign('purchase_invoice_item_id', 'gr_invoice_alloc_invoice_item_fk')->references('id')->on('purchase_invoice_items')->restrictOnDelete();
            $table->foreign('goods_receipt_id', 'gr_invoice_alloc_receipt_fk')->references('id')->on('goods_receipts')->restrictOnDelete();
            $table->foreign('goods_receipt_item_id', 'gr_invoice_alloc_receipt_item_fk')->references('id')->on('goods_receipt_items')->restrictOnDelete();
        });
        Schema::create('purchase_invoice_cash_payments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('purchase_invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 40);
            $table->string('method', 24)->default('cash');
            $table->decimal('amount', 24, 2)->unsigned();
            $table->timestampTz('paid_at');
            $table->string('reference', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'payment_number'], 'purchase_invoice_cash_payments_number_unique');
        });
        Schema::create('ap_entries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_payment_id')->nullable()->constrained('purchase_invoice_cash_payments')->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->decimal('amount_delta', 24, 2);
            $table->date('due_date')->nullable();
            $table->string('source_key', 190)->unique();
            $table->timestampTz('occurred_at');
            $table->string('description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'supplier_id', 'due_date'], 'ap_entries_supplier_due_index');
            $table->index(['purchase_invoice_id', 'entry_type'], 'ap_entries_invoice_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ap_entries');
        Schema::dropIfExists('purchase_invoice_cash_payments');
        Schema::dropIfExists('goods_receipt_invoice_allocations');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
