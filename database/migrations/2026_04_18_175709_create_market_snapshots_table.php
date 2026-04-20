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
        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_symbol');
            $table->decimal('price_usd', 18, 8);
            $table->string('source')->default('coingecko');
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['user_id', 'asset_symbol']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_snapshots');
    }
};
