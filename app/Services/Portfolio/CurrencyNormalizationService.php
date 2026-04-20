<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

class CurrencyNormalizationService
{
    /**
     * Injeta serviço de câmbio para conversão BRL→USD sob demanda.
     */
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    /**
     * Normaliza o preço médio para USD antes de qualquer cálculo.
     *
     * @return array{average_price_usd: string, exchange_rate_used: ?string}
     */
    public function normalizeToUsd(string $averagePriceInput, string $inputCurrency): array
    {
        $normalizedInputValue = $this->toCurrencyValue($averagePriceInput);

        if ($inputCurrency === 'USD') {
            return [
                'average_price_usd' => $normalizedInputValue,
                'exchange_rate_used' => null,
            ];
        }

        // Para BRL, convertemos usando taxa explícita e armazenamos a taxa usada para auditoria.
        $brlPerUsd = $this->exchangeRateService->getBrlPerUsdRate();
        $averagePriceUsd = bcdiv($normalizedInputValue, $brlPerUsd, ProfitAndLossCalculator::SCALE);

        return [
            'average_price_usd' => $averagePriceUsd,
            'exchange_rate_used' => $brlPerUsd,
        ];
    }

    /**
     * Converte entrada em centavos para valor monetário textual (escala fixa).
     */
    private function toCurrencyValue(string $averagePriceInput): string
    {
        if (preg_match('/^\d+$/', $averagePriceInput) === 1) {
            return bcdiv($averagePriceInput, '100', ProfitAndLossCalculator::SCALE);
        }

        return bcadd($averagePriceInput, '0', ProfitAndLossCalculator::SCALE);
    }
}
