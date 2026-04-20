<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioPosition>
 */
class PortfolioPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Factory com valores determinísticos em string decimal para testes de precisão.
            'user_id' => User::factory(),
            'asset_symbol' => fake()->randomElement(['BTC', 'ETH', 'SOL']),
            'quantity' => '1.25000000',
            'average_price_input' => '100.00000000',
            'input_currency' => 'USD',
            'average_price_usd' => '100.00000000',
            'exchange_rate_used' => null,
        ];
    }
}
