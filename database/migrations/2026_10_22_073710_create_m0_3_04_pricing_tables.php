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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('IDR');
            $table->decimal('minimum_quantity', 18, 3)->default(1);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['product_id', 'price_list_id', 'unit_id', 'minimum_quantity', 'effective_from'],
                'product_prices_effective_unique',
            );
            $table->index(
                ['product_id', 'price_list_id', 'unit_id', 'is_active', 'effective_from', 'effective_to'],
                'product_prices_effective_lookup_index',
            );
        });

        Schema::create('price_change_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('product_price_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('change_type', 32);
            $table->decimal('previous_amount', 18, 2)->nullable();
            $table->decimal('new_amount', 18, 2);
            $table->dateTime('previous_effective_from')->nullable();
            $table->dateTime('previous_effective_to')->nullable();
            $table->dateTime('new_effective_from');
            $table->dateTime('new_effective_to')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->index(['product_id', 'changed_at']);
            $table->index(['price_list_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_change_logs');
        Schema::dropIfExists('product_prices');
    }
};
