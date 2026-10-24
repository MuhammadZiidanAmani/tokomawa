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
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('register_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->decimal('opening_float', 24, 2)->unsigned();
            $table->timestampTz('opened_at');
            $table->string('active_register_key', 80)->nullable()->unique();
            $table->string('active_cashier_key', 80)->nullable()->unique();
            $table->uuid('opening_key')->unique();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->index(['branch_id', 'status', 'opened_at'], 'cash_shifts_branch_status_date_index');
            $table->index(['register_id', 'status'], 'cash_shifts_register_status_index');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('register_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_number', 40);
            $table->string('status', 24)->default('posting');
            $table->timestampTz('sold_at');
            $table->char('currency', 3)->default('IDR');
            $table->decimal('subtotal', 24, 2)->unsigned();
            $table->decimal('discount_total', 24, 2)->unsigned()->default(0);
            $table->decimal('tax_total', 24, 2)->unsigned()->default(0);
            $table->decimal('grand_total', 24, 2)->unsigned();
            $table->decimal('cash_tendered', 24, 2)->unsigned();
            $table->decimal('change_due', 24, 2)->unsigned();
            $table->uuid('posting_key')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'document_number'], 'sales_branch_document_unique');
            $table->index(['branch_id', 'status', 'sold_at'], 'sales_branch_status_date_index');
            $table->index(['cash_shift_id', 'sold_at'], 'sales_shift_date_index');
            $table->index(['cashier_id', 'sold_at'], 'sales_cashier_date_index');
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_attribution_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('product_sku_snapshot', 64);
            $table->string('product_name_snapshot');
            $table->string('unit_code_snapshot', 32);
            $table->string('unit_name_snapshot', 100);
            $table->string('unit_symbol_snapshot', 24);
            $table->string('batch_lot_snapshot', 100)->nullable();
            $table->date('batch_expiry_snapshot')->nullable();
            $table->decimal('conversion_factor', 18, 6)->unsigned();
            $table->decimal('quantity', 18, 3)->unsigned();
            $table->decimal('base_quantity', 18, 3)->unsigned();
            $table->decimal('unit_price_snapshot', 18, 2)->unsigned();
            $table->string('price_list_code_snapshot', 40);
            $table->string('price_list_name_snapshot');
            $table->decimal('subtotal', 24, 2)->unsigned();
            $table->decimal('discount_amount', 24, 2)->unsigned()->default(0);
            $table->foreignId('tax_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tax_code_snapshot', 64)->nullable();
            $table->decimal('tax_rate_snapshot', 7, 4)->nullable();
            $table->decimal('tax_amount', 24, 2)->unsigned()->default(0);
            $table->decimal('line_total', 24, 2)->unsigned();
            $table->decimal('cogs_unit_snapshot', 18, 6)->unsigned();
            $table->decimal('cogs_total_snapshot', 24, 6)->unsigned();
            $table->ulid('inventory_movement_id');
            $table->timestamps();
            $table->unique(['sale_id', 'line_number'], 'sale_items_line_unique');
            $table->unique('inventory_movement_id', 'sale_items_inventory_movement_unique');
            $table->index(['product_id', 'sale_id'], 'sale_items_product_sale_index');
            $table->foreign('inventory_movement_id', 'sale_items_inventory_movement_fk')->references('id')->on('inventory_movements')->restrictOnDelete();
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->string('method', 20)->default('cash');
            $table->string('status', 20)->default('settled');
            $table->decimal('amount', 24, 2)->unsigned();
            $table->decimal('tendered_amount', 24, 2)->unsigned();
            $table->decimal('change_amount', 24, 2)->unsigned();
            $table->string('source_key', 190)->unique();
            $table->timestampTz('paid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['cash_shift_id', 'paid_at'], 'sale_payments_shift_date_index');
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('register_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('amount_delta', 24, 2);
            $table->string('source_key', 190)->unique();
            $table->string('description');
            $table->timestampTz('occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cash_shift_id', 'occurred_at'], 'cash_movements_shift_date_index');
            $table->index(['branch_id', 'movement_type', 'occurred_at'], 'cash_movements_branch_type_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_shifts');
    }
};
