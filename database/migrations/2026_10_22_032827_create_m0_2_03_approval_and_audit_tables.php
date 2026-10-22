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
        Schema::create('approval_limits', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_amount', 18, 2)->default(0);
            $table->decimal('maximum_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'role_id', 'permission_id']);
            $table->index(['branch_id', 'permission_id', 'is_active'], 'approval_limits_lookup_index');
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('permission_code', 120);
            $table->string('entity_type', 150);
            $table->string('entity_id', 80);
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestampTz('requested_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'requested_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('role_snapshot');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120);
            $table->string('entity_type', 150);
            $table->string('entity_id', 80);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at');

            $table->index(['branch_id', 'created_at']);
            $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_entity_created_index');
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_limits');
    }
};
