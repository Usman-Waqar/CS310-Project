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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('source_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('destination_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('COMPLETED');
            $table->string('idempotency_key', 64)->unique();
            $table->boolean('is_refunded')->default(false);
            $table->string('description', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['initiated_by_user_id', 'created_at', 'id']);
            $table->index(['source_wallet_id', 'created_at', 'id']);
            $table->index(['destination_wallet_id', 'created_at', 'id']);
            $table->index(['initiated_by_user_id', 'type', 'is_refunded', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
