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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope', 80)->default('global');
            $table->string('operation', 120);
            $table->string('key', 120);
            $table->char('request_hash', 64);
            $table->string('status', 32)->default('processing');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'operation', 'key']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'document_type', 'year', 'month'], 'document_sequences_scope_unique');
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 150);
            $table->string('aggregate_type', 150);
            $table->string('aggregate_id', 80);
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['published_at', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('idempotency_keys');
    }
};
