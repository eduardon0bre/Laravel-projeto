<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

class ProfitAndLossCalculator
{
    /**
     * Escala padrão de precisão para operações financeiras com BCMath.
     */
    public const SCALE = 8;

    public const ZERO = '0.00000000';

    /**
     * Calcula investido/atual/lucro em USD para uma posição específica.
     *
     * @return array{
     *     invested_value_usd: string,
     *     current_value_usd: ?string,
     *     profit_loss_usd: ?string,
     *     profit_percentage: ?string
     * }
     */
    public function calculatePosition(
        string $quantity,
        string $averagePriceUsd,
        ?string $currentPriceUsd,
    ): array {
        // Investido sempre pode ser calculado, pois depende apenas de dados normalizados já persistidos.
        $investedValueUsd = bcmul($quantity, $averagePriceUsd, self::SCALE);

        // Se não houver snapshot salvo, mantemos os campos de mercado como nulos.
        if ($currentPriceUsd === null) {
            return [
                'invested_value_usd' => $investedValueUsd,
                'current_value_usd' => null,
                'profit_loss_usd' => null,
                'profit_percentage' => null,
            ];
        }

        $currentValueUsd = bcmul($quantity, $currentPriceUsd, self::SCALE);
        $profitLossUsd = bcsub($currentValueUsd, $investedValueUsd, self::SCALE);

        // Regra financeira: percentual sempre usa invested_value como denominador.
        // Multiplicamos por 100 para retornar valor em percentuais (ex.: 40.00000000).
        $profitPercentage = bccomp($investedValueUsd, self::ZERO, self::SCALE) === 0
            ? null
            : bcmul(
                bcdiv($profitLossUsd, $investedValueUsd, self::SCALE),
                '100',
                self::SCALE,
            );

        return [
            'invested_value_usd' => $investedValueUsd,
            'current_value_usd' => $currentValueUsd,
            'profit_loss_usd' => $profitLossUsd,
            'profit_percentage' => $profitPercentage,
        ];
    }

    /**
     * Soma textual com precisão fixa para totais do portfólio.
     */
    public function add(string $left, string $right): string
    {
        return bcadd($left, $right, self::SCALE);
    }
}
