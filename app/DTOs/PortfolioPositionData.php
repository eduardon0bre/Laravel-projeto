<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO imutável que representa o estado financeiro calculado de uma posição.
 */
readonly class PortfolioPositionData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $id,
        public string $assetSymbol,
        public ?string $assetTitle,
        public string $quantity,
        public string $averagePriceInput,
        public string $inputCurrency,
        public string $averagePriceUsd,
        public ?string $exchangeRateUsed,
        public ?string $currentPriceUsd,
        public ?string $investedValueUsd,
        public ?string $currentValueUsd,
        public ?string $profitLossUsd,
        public ?string $profitPercentage,
        public array $meta = [],
    ) {}

    /**
     * Serialização explícita evita ambiguidade entre nomes internos/externos.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'asset_symbol' => $this->assetSymbol,
            'asset_title' => $this->assetTitle,
            'quantity' => $this->quantity,
            'average_price_input' => $this->averagePriceInput,
            'input_currency' => $this->inputCurrency,
            'average_price_usd' => $this->averagePriceUsd,
            'exchange_rate_used' => $this->exchangeRateUsed,
            'current_price_usd' => $this->currentPriceUsd,
            'invested_value_usd' => $this->investedValueUsd,
            'current_value_usd' => $this->currentValueUsd,
            'profit_loss_usd' => $this->profitLossUsd,
            'profit_percentage' => $this->profitPercentage,
            'meta' => $this->meta,
        ];
    }
}
