<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Asset::query()->truncate();

        $now = now();

        $assets = [
            [
                'ticker' => 'BTC',
                'name' => 'Bitcoin',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'ETH',
                'name' => 'Ethereum',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'USDT',
                'name' => 'Tether USDt',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'BNB',
                'name' => 'BNB',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'SOL',
                'name' => 'Solana',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'XRP',
                'name' => 'XRP',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'USDC',
                'name' => 'USD Coin',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'ADA',
                'name' => 'Cardano',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'AVAX',
                'name' => 'Avalanche',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticker' => 'DOGE',
                'name' => 'Dogecoin',
                'type' => 'CRYPTO',
                'currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        Asset::query()->insert($assets);
    }
}
