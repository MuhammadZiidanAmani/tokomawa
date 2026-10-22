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
        Schema::create('shelf_locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('zone', 40)->nullable();
            $table->string('aisle', 40)->nullable();
            $table->string('level', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['branch_id', 'warehouse_id', 'is_active'], 'shelf_locations_scope_index');
        });

        Schema::create('product_locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('shelf_location_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'shelf_location_id']);
            $table->index(['shelf_location_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_locations');
        Schema::dropIfExists('shelf_locations');
    }
};
