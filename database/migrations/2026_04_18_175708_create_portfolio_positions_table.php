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
        Schema::create('portfolio_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_symbol');
            $table->decimal('quantity', 18, 8);
            $table->decimal('average_price_input', 18, 8);
            $table->string('input_currency', 3);
            $table->decimal('average_price_usd', 18, 8);
            $table->decimal('exchange_rate_used', 18, 8)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'asset_symbol']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_positions');
    }
};
