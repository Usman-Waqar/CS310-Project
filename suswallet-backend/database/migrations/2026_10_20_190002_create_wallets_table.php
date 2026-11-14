<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->boolean('is_main')->default(false);
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->char('currency', 3)->default('TRY');
            $table->timestamps();

            $table->unique(['user_id', 'vendor_id']);
            $table->index(['user_id', 'is_main']);
        });

        // Partial unique index ensures each user has at most one Main Wallet (vendor_id IS NULL)
        DB::statement('CREATE UNIQUE INDEX uq_wallets_user_main ON wallets (user_id) WHERE is_main = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
