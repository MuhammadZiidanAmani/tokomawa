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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name')->index();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_identifier', 64)->nullable();
            $table->string('contact_name')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('sku', 64)->unique();
            $table->string('name')->index();
            $table->string('classification', 32);
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('track_batch')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('allow_fractional_qty')->default(false);
            $table->decimal('min_stock', 18, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['classification', 'is_active']);
            $table->index(['department_id', 'category_id', 'subcategory_id'], 'products_hierarchy_index');
            $table->index(['default_supplier_id', 'is_active']);
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('barcode', 64)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'is_active', 'is_primary']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6);
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_sale_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('supplier_sku', 80)->nullable();
            $table->decimal('last_purchase_price', 18, 2)->nullable();
            $table->decimal('contract_price', 18, 2)->nullable();
            $table->decimal('minimum_order_qty', 18, 3)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
            $table->unique(['supplier_id', 'supplier_sku']);
            $table->index(['product_id', 'is_primary', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
    }
};
