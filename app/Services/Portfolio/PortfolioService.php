<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\DTOs\PortfolioPositionData;
use App\DTOs\PortfolioSummaryData;
use App\Models\Asset;
use App\Models\MarketSnapshot;
use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortfolioService
{
    /**
     * Serviço principal que orquestra persistência + cálculo determinístico.
     */
    public function __construct(
        private readonly CurrencyNormalizationService $currencyNormalizationService,
        private readonly MarketPriceService $marketPriceService,
        private readonly ProfitAndLossCalculator $profitAndLossCalculator,
    ) {}

    /**
     * Retorna estado atual do portfólio baseado exclusivamente em dados salvos.
     *
     * @return array{positions: array<int, PortfolioPositionData>, summary: PortfolioSummaryData}
     */
    public function list(User $user): array
    {
        $positions = PortfolioPosition::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->buildResult($user, $positions);
    }

    /**
     * Salva ou atualiza uma posição, normalizando para USD antes de persistir.
     *
     * @param  array{
     *     asset_symbol: string,
     *     quantity: string,
     *     average_price_input: string,
     *     input_currency: string
     * }  $payload
     */
    public function store(User $user, array $payload): void
    {
        DB::transaction(function () use ($user, $payload): void {
            $normalized = $this->currencyNormalizationService->normalizeToUsd(
                averagePriceInput: $payload['average_price_input'],
                inputCurrency: $payload['input_currency'],
            );

            $position = PortfolioPosition::query()
                ->where('user_id', $user->id)
                ->where('asset_symbol', $payload['asset_symbol'])
                ->lockForUpdate()
                ->first();

            if ($position === null) {
                PortfolioPosition::query()->create([
                    'user_id' => $user->id,
                    'asset_symbol' => $payload['asset_symbol'],
                    'quantity' => $payload['quantity'],
                    'average_price_input' => $payload['average_price_input'],
                    'input_currency' => $payload['input_currency'],
                    'average_price_usd' => $normalized['average_price_usd'],
                    'exchange_rate_used' => $normalized['exchange_rate_used'],
                ]);

                return;
            }

            $totalQuantity = bcadd(
                (string) $position->quantity,
                $payload['quantity'],
                ProfitAndLossCalculator::SCALE,
            );

            $existingTotalUsd = bcmul(
                (string) $position->quantity,
                (string) $position->average_price_usd,
                ProfitAndLossCalculator::SCALE,
            );

            $incomingTotalUsd = bcmul(
                $payload['quantity'],
                $normalized['average_price_usd'],
                ProfitAndLossCalculator::SCALE,
            );

            $weightedAverageUsd = bccomp(
                $totalQuantity,
                ProfitAndLossCalculator::ZERO,
                ProfitAndLossCalculator::SCALE,
            ) === 1
                ? bcdiv(
                    bcadd($existingTotalUsd, $incomingTotalUsd, ProfitAndLossCalculator::SCALE),
                    $totalQuantity,
                    ProfitAndLossCalculator::SCALE,
                )
                : $normalized['average_price_usd'];

            $weightedAverageInput = $payload['input_currency'] === 'BRL'
                ? bcmul(
                    $weightedAverageUsd,
                    $normalized['exchange_rate_used'] ?? '1',
                    ProfitAndLossCalculator::SCALE,
                )
                : $weightedAverageUsd;

            $position->update([
                'quantity' => $totalQuantity,
                'average_price_input' => $this->toStoredInputValue(
                    averagePriceInput: $weightedAverageInput,
                    inputCurrency: $payload['input_currency'],
                ),
                'input_currency' => $payload['input_currency'],
                'average_price_usd' => $weightedAverageUsd,
                'exchange_rate_used' => $normalized['exchange_rate_used'],
            ]);
        });
    }

    /**
     * Atualiza snapshots somente sob ação explícita do usuário.
     *
     * @param  array<int, string>|null  $assetSymbols
     */
    public function refresh(User $user, ?array $assetSymbols = null): void
    {
        $positions = PortfolioPosition::query()
            ->where('user_id', $user->id)
            ->when($assetSymbols !== null, function ($query) use ($assetSymbols) {
                $query->whereIn('asset_symbol', $assetSymbols);
            })
            ->get(['asset_symbol']);

        $symbolsToRefresh = $positions
            ->pluck('asset_symbol')
            ->unique()
            ->values()
            ->all();

        if ($symbolsToRefresh === []) {
            return;
        }

        $latestUsdPrices = $this->marketPriceService->getUsdPrices($symbolsToRefresh);

        DB::transaction(function () use ($user, $latestUsdPrices): void {
            foreach ($latestUsdPrices as $assetSymbol => $priceUsd) {
                // Apenas preços validados chegam aqui. Snapshot inválido nunca sobrescreve estado válido.
                MarketSnapshot::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'asset_symbol' => $assetSymbol,
                    ],
                    [
                        'price_usd' => $priceUsd,
                        'source' => 'coinmarketcap',
                        'captured_at' => now(),
                    ],
                );
            }
        });
    }

    /**
     * Remove posição e snapshot correlato de forma atômica.
     */
    public function delete(User $user, PortfolioPosition $position): void
    {
        DB::transaction(function () use ($user, $position): void {
            $assetSymbol = $position->asset_symbol;

            $position->delete();

            // Limpa snapshot órfão do ativo removido para manter estado enxuto e coerente.
            MarketSnapshot::query()
                ->where('user_id', $user->id)
                ->where('asset_symbol', $assetSymbol)
                ->delete();
        });
    }

    /**
     * @param  Collection<int, PortfolioPosition>  $positions
     * @return array{positions: array<int, PortfolioPositionData>, summary: PortfolioSummaryData}
     */
    private function buildResult(User $user, Collection $positions): array
    {
        if ($positions->isEmpty()) {
            return [
                'positions' => [],
                'summary' => new PortfolioSummaryData(
                    positionsCount: 0,
                    investedTotalUsd: ProfitAndLossCalculator::ZERO,
                    currentTotalUsd: ProfitAndLossCalculator::ZERO,
                    profitLossTotalUsd: ProfitAndLossCalculator::ZERO,
                    meta: [
                        'uses_saved_snapshots_only' => true,
                        'auto_refresh_enabled' => false,
                    ],
                ),
            ];
        }

        $symbols = $positions->pluck('asset_symbol')->all();

        $snapshotsBySymbol = MarketSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('asset_symbol', $symbols)
            ->get()
            ->keyBy('asset_symbol');

        $positionsData = [];
        $investedTotalUsd = ProfitAndLossCalculator::ZERO;
        $currentTotalUsd = ProfitAndLossCalculator::ZERO;
        $profitLossTotalUsd = ProfitAndLossCalculator::ZERO;

        // Mapeia slug -> título para exibição amigável sem cálculo no frontend.
        $assetTitleBySlug = Asset::query()
            ->whereIn('ticker', $symbols)
            ->get(['ticker', 'name'])
            ->mapWithKeys(fn(Asset $asset): array => [
                Str::upper((string) $asset->ticker) => (string) $asset->name,
            ]);

        foreach ($positions as $position) {
            $snapshot = $snapshotsBySymbol->get($position->asset_symbol);
            $calculation = $this->profitAndLossCalculator->calculatePosition(
                quantity: (string) $position->quantity,
                averagePriceUsd: (string) $position->average_price_usd,
                currentPriceUsd: $snapshot?->price_usd !== null ? (string) $snapshot->price_usd : null,
            );

            $positionsData[] = new PortfolioPositionData(
                id: $position->id,
                assetSymbol: (string) $position->asset_symbol,
                assetTitle: $assetTitleBySlug->get((string) $position->asset_symbol),
                quantity: (string) $position->quantity,
                averagePriceInput: (string) $position->average_price_input,
                inputCurrency: Str::upper((string) $position->input_currency),
                averagePriceUsd: (string) $position->average_price_usd,
                exchangeRateUsed: $position->exchange_rate_used !== null
                    ? (string) $position->exchange_rate_used
                    : null,
                currentPriceUsd: $snapshot?->price_usd !== null ? (string) $snapshot->price_usd : null,
                investedValueUsd: $calculation['invested_value_usd'],
                currentValueUsd: $calculation['current_value_usd'],
                profitLossUsd: $calculation['profit_loss_usd'],
                profitPercentage: $calculation['profit_percentage'],
                meta: [
                    'snapshot_captured_at' => $snapshot?->captured_at?->toISOString(),
                    'snapshot_source' => $snapshot?->source,
                ],
            );

            $investedTotalUsd = $this->profitAndLossCalculator->add(
                $investedTotalUsd,
                $calculation['invested_value_usd'],
            );

            if ($calculation['current_value_usd'] !== null && $calculation['profit_loss_usd'] !== null) {
                $currentTotalUsd = $this->profitAndLossCalculator->add(
                    $currentTotalUsd,
                    $calculation['current_value_usd'],
                );
                $profitLossTotalUsd = $this->profitAndLossCalculator->add(
                    $profitLossTotalUsd,
                    $calculation['profit_loss_usd'],
                );
            }
        }

        return [
            'positions' => $positionsData,
            'summary' => new PortfolioSummaryData(
                positionsCount: count($positionsData),
                investedTotalUsd: $investedTotalUsd,
                currentTotalUsd: $currentTotalUsd,
                profitLossTotalUsd: $profitLossTotalUsd,
                meta: [
                    'uses_saved_snapshots_only' => true,
                    'auto_refresh_enabled' => false,
                ],
            ),
        ];
    }

    /**
     * Armazena preço de entrada em centavos para BRL/USD sem usar float.
     */
    private function toStoredInputValue(string $averagePriceInput, string $inputCurrency): string
    {
        if ($inputCurrency !== 'BRL' && $inputCurrency !== 'USD') {
            return $averagePriceInput;
        }

        return bcmul($averagePriceInput, '100', ProfitAndLossCalculator::SCALE);
    }
}
