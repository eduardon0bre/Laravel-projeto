<?php

use App\Models\Asset;
use App\Models\MarketSnapshot;
use App\Models\PortfolioPosition;
use App\Models\User;
use Database\Seeders\AssetSeeder;
use Illuminate\Support\Facades\Http;

const BASE_PRICE_USD = '100.00000000';
const MARKET_PRICE_USD = '150.00000000';
const TOTAL_QUANTITY = '2.00000000';

test('stores usd position without currency conversion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('portfolio.store'), [
        'asset_symbol' => 'btc',
        'quantity' => TOTAL_QUANTITY,
        'average_price_input' => '10000',
        'input_currency' => 'USD',
    ]);

    $response->assertCreated();

    $position = PortfolioPosition::query()
        ->where('user_id', $user->id)
        ->where('asset_symbol', 'BTC')
        ->firstOrFail();

    // Entrada USD em centavos: 10000 = 100.00000000 USD.
    expect((string) $position->average_price_usd)->toBe(BASE_PRICE_USD)
        ->and($position->exchange_rate_used)->toBeNull();
});

test('stores brl position with explicit usd normalization and exchange traceability', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Mock de taxa BRL por USD: 5 BRL = 1 USD.
    Http::fake([
        'api.exchangerate.host/*' => Http::response([
            'rates' => [
                'BRL' => '5.00000000',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('portfolio.store'), [
        'asset_symbol' => 'eth',
        'quantity' => '3.00000000',
        'average_price_input' => '50000',
        'input_currency' => 'BRL',
    ]);

    $response->assertCreated();

    $position = PortfolioPosition::query()
        ->where('user_id', $user->id)
        ->where('asset_symbol', 'ETH')
        ->firstOrFail();

    // 50000 centavos BRL = 500 BRL; 500 / 5 = 100 USD.
    expect((string) $position->average_price_usd)->toBe(BASE_PRICE_USD)
        ->and((string) $position->exchange_rate_used)->toBe('5.00000000');
});

test('aggregates quantity and weighted average when buying an existing position', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $firstPurchase = $this->postJson(route('portfolio.store'), [
        'asset_symbol' => 'btc',
        'quantity' => '1.25000000',
        'average_price_input' => '10000',
        'input_currency' => 'USD',
    ]);

    $firstPurchase->assertCreated();

    $secondPurchase = $this->postJson(route('portfolio.store'), [
        'asset_symbol' => 'btc',
        'quantity' => '0.75000000',
        'average_price_input' => '20000',
        'input_currency' => 'USD',
    ]);

    $secondPurchase->assertCreated();

    $position = PortfolioPosition::query()
        ->where('user_id', $user->id)
        ->where('asset_symbol', 'BTC')
        ->firstOrFail();

    // Quantidade total: 1.25 + 0.75 = 2.00.
    expect((string) $position->quantity)->toBe(TOTAL_QUANTITY);

    // Preço médio ponderado USD: ((1.25*100) + (0.75*200)) / 2.00 = 137.5.
    expect((string) $position->average_price_usd)->toBe('137.50000000');
});

test('does not auto-refresh market data when viewing portfolio', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    PortfolioPosition::factory()->create([
        'user_id' => $user->id,
        'asset_symbol' => 'BTC',
    ]);

    Http::fake();

    $response = $this->getJson(route('portfolio.index'));

    $response->assertOk();

    // Regra crítica: visualizar portfólio não deve disparar chamadas externas.
    Http::assertNothingSent();
});

test('refresh updates snapshot only on user action and computes deterministic usd values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    PortfolioPosition::factory()->create([
        'user_id' => $user->id,
        'asset_symbol' => 'BTC',
        'quantity' => TOTAL_QUANTITY,
        'average_price_input' => BASE_PRICE_USD,
        'input_currency' => 'USD',
        'average_price_usd' => BASE_PRICE_USD,
        'exchange_rate_used' => null,
    ]);

    Http::fake([
        'pro-api.coinmarketcap.com/*' => Http::response([
            'data' => [
                'BTC' => [[
                    'quote' => [
                        'USD' => [
                            'price' => MARKET_PRICE_USD,
                        ],
                    ],
                ]],
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('portfolio.refresh'));

    $response->assertOk()
        ->assertJsonPath('data.0.current_price_usd', MARKET_PRICE_USD)
        ->assertJsonPath('data.0.invested_value_usd', '200.00000000')
        ->assertJsonPath('data.0.current_value_usd', '300.00000000')
        ->assertJsonPath('data.0.profit_loss_usd', BASE_PRICE_USD)
        // 100 / 200 * 100 = 50.00% (denominador: invested_value_usd).
        ->assertJsonPath('data.0.profit_percentage', '50.00000000');

    $snapshot = MarketSnapshot::query()
        ->where('user_id', $user->id)
        ->where('asset_symbol', 'BTC')
        ->firstOrFail();

    expect((string) $snapshot->price_usd)->toBe(MARKET_PRICE_USD);
});

test('refresh preserves snapshot on failure and delete removes state atomically', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $position = PortfolioPosition::factory()->create([
        'user_id' => $user->id,
        'asset_symbol' => 'BTC',
    ]);

    MarketSnapshot::factory()->create([
        'user_id' => $user->id,
        'asset_symbol' => 'BTC',
        'price_usd' => MARKET_PRICE_USD,
    ]);

    // Payload inválido da API deve falhar sem sobrescrever snapshot válido.
    Http::fake([
        'pro-api.coinmarketcap.com/*' => Http::response([
            'data' => [
                'BTC' => [[
                    'quote' => [
                        'USD' => [
                            'price' => 'invalid',
                        ],
                    ],
                ]],
            ],
        ], 200),
    ]);

    $refreshResponse = $this->postJson(route('portfolio.refresh'));
    $refreshResponse->assertUnprocessable();

    expect(MarketSnapshot::query()
        ->where('user_id', $user->id)
        ->where('asset_symbol', 'BTC')
        ->firstOrFail()
        ->price_usd)
        ->toBe(MARKET_PRICE_USD);

    $deleteResponse = $this->deleteJson(route('portfolio.destroy', $position));
    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('portfolio_positions', [
        'id' => $position->id,
    ]);

    $this->assertDatabaseMissing('market_snapshots', [
        'user_id' => $user->id,
        'asset_symbol' => 'BTC',
    ]);
});

test('asset catalog endpoint includes solana reference when seeded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->seed(AssetSeeder::class);

    $response = $this->getJson(route('portfolio.assets'));

    $response->assertOk();

    expect(collect($response->json('data'))
        ->pluck('slug'))
        ->toContain('SOL');
});

test('asset seeder keeps only top 10 crypto tickers', function () {
    $this->seed(AssetSeeder::class);

    $seededTickers = Asset::query()
        ->orderBy('ticker')
        ->pluck('ticker')
        ->all();

    expect($seededTickers)
        ->toBe([
            'ADA',
            'AVAX',
            'BNB',
            'BTC',
            'DOGE',
            'ETH',
            'SOL',
            'USDC',
            'USDT',
            'XRP',
        ]);
});
