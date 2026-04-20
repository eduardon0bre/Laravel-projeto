<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MarketSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketSnapshot>
 */
class MarketSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_symbol' => fake()->randomElement(['BTC', 'ETH', 'SOL']),
            'price_usd' => '150.00000000',
            'source' => 'coinmarketcap',
            'captured_at' => now(),
        ];
    }
}
