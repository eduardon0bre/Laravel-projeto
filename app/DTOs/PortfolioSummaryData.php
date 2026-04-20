<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO imutável com agregados totais do portfólio em USD.
 */
readonly class PortfolioSummaryData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $positionsCount,
        public string $investedTotalUsd,
        public string $currentTotalUsd,
        public string $profitLossTotalUsd,
        public array $meta = [],
    ) {}

    /**
     * Retorna shape estável para consumo pela camada de Resource/API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'positions_count' => $this->positionsCount,
            'invested_total_usd' => $this->investedTotalUsd,
            'current_total_usd' => $this->currentTotalUsd,
            'profit_loss_total_usd' => $this->profitLossTotalUsd,
            'meta' => $this->meta,
        ];
    }
}
