<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('lot_number', 100);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'product_id', 'lot_number']);
            $table->index(['branch_id', 'expires_at', 'is_active']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('document_number', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('draft');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('occurred_at');
            $table->foreignId('approval_request_id')->nullable()->unique()->constrained('approval_requests')->restrictOnDelete();
            $table->string('submission_key', 120)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'occurred_at']);
            $table->index(['branch_id', 'type', 'occurred_at']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('stock_adjustment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('base_quantity', 18, 3);
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->string('stock_status', 32)->default('sellable');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['stock_adjustment_id', 'line_number']);
            $table->index(['product_id', 'inventory_batch_id']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->string('batch_scope', 26)->default('NO-BATCH');
            $table->string('stock_status', 32)->default('sellable');
            $table->decimal('qty_on_hand', 18, 3)->default(0);
            $table->decimal('qty_reserved', 18, 3)->default(0);
            $table->decimal('avg_unit_cost', 18, 6)->default(0);
            $table->decimal('inventory_value', 24, 6)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->unique(
                ['warehouse_id', 'product_id', 'batch_scope', 'stock_status'],
                'inventory_balances_scope_unique',
            );
            $table->index(['branch_id', 'product_id', 'stock_status']);
            $table->index(['warehouse_id', 'stock_status']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->string('batch_scope', 26)->default('NO-BATCH');
            $table->string('stock_status', 32)->default('sellable');
            $table->string('movement_type', 40);
            $table->string('direction', 3);
            $table->decimal('qty_delta', 18, 3);
            $table->decimal('qty_after', 18, 3);
            $table->decimal('unit_cost_snapshot', 18, 6);
            $table->decimal('value_delta', 24, 6);
            $table->string('source_type', 80);
            $table->string('source_id', 80);
            $table->string('source_line_id', 80);
            $table->string('source_key', 190)->unique();
            $table->string('idempotency_key', 120);
            $table->timestampTz('occurred_at');
            $table->timestampTz('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('reversal_of_id')->nullable();
            $table->timestampTz('created_at');
            $table->foreign('reversal_of_id')->references('id')->on('inventory_movements')->restrictOnDelete();
            $table->index(['branch_id', 'product_id', 'occurred_at']);
            $table->index(['warehouse_id', 'product_id', 'stock_status']);
            $table->index(['source_type', 'source_id']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('inventory_batches');
    }
};
