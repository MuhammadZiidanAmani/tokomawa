<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 40);
            $table->string('status', 32)->default('draft');
            $table->date('order_date');
            $table->date('expected_at')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->string('supplier_code_snapshot', 40);
            $table->string('supplier_name_snapshot');
            $table->text('supplier_address_snapshot')->nullable();
            $table->decimal('subtotal', 24, 2)->unsigned()->default(0);
            $table->decimal('grand_total', 24, 2)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approval_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->uuid('creation_key')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'document_number'], 'purchase_orders_branch_document_unique');
            $table->index(['branch_id', 'status', 'order_date'], 'purchase_orders_branch_status_date_index');
            $table->index(['supplier_id', 'status', 'order_date'], 'purchase_orders_supplier_status_date_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_sku_snapshot', 64);
            $table->string('product_name_snapshot');
            $table->string('supplier_sku_snapshot', 80)->nullable();
            $table->string('unit_code_snapshot', 32);
            $table->string('unit_name_snapshot', 100);
            $table->string('unit_symbol_snapshot', 24);
            $table->decimal('conversion_factor', 18, 6)->unsigned();
            $table->decimal('quantity', 18, 3)->unsigned();
            $table->decimal('base_quantity', 18, 3)->unsigned();
            $table->decimal('received_base_quantity', 18, 3)->unsigned()->default(0);
            $table->decimal('unit_price', 18, 2)->unsigned();
            $table->decimal('subtotal', 24, 2)->unsigned();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_number'], 'purchase_order_items_line_unique');
            $table->unique(['purchase_order_id', 'product_id'], 'purchase_order_items_product_unique');
            $table->index(['product_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
