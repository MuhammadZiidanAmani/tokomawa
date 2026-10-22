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
        Schema::create('departments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'code']);
            $table->index(['department_id', 'is_active', 'name']);
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'code']);
            $table->index(['category_id', 'is_active', 'name']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('symbol', 20);
            $table->boolean('allow_fractional')->default(false);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 64);
            $table->string('name');
            $table->decimal('rate', 7, 4);
            $table->string('calculation_mode', 20);
            $table->string('rounding_mode', 32);
            $table->unsignedTinyInteger('rounding_scale')->default(2);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['code', 'effective_from']);
            $table->index(['code', 'is_active', 'effective_from', 'effective_to'], 'tax_rules_effective_lookup_index');
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('departments');
    }
};
