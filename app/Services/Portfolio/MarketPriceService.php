<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Exceptions\Portfolio\MarketDataResponseException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MarketPriceService
{
    /**
     * Busca preços atuais em USD para uma lista de ativos.
     *
     * @param  array<int, string>  $assetSymbols
     * @return array<string, string>
     */
    public function getUsdPrices(array $assetSymbols): array
    {
        if ($assetSymbols === []) {
            return [];
        }

        $normalizedSymbols = collect($assetSymbols)
            ->map(fn (string $symbol): string => strtoupper($symbol))
            ->unique()
            ->values()
            ->all();

        $apiKey = (string) config('services.market_data.api_key');

        if ($apiKey === '') {
            throw new MarketDataResponseException('COIN_MARKETCAP_API_KEY não está configurada.');
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-CMC_PRO_API_KEY' => $apiKey,
            ])
            ->connectTimeout((int) config('services.market_data.connect_timeout', 5))
            ->timeout((int) config('services.market_data.timeout', 10))
            ->retry([250, 500, 1000])
            ->get((string) config('services.market_data.url'), [
                'symbol' => implode(',', $normalizedSymbols),
                'convert' => 'USD',
            ]);

        $this->ensureValidResponse($response);

        $payload = $response->json();
        $validatedPrices = [];

        foreach ($normalizedSymbols as $symbol) {
            $price = data_get($payload, 'data.'.$symbol.'.0.quote.USD.price');

            // Só aceitamos preço numérico positivo em USD.
            $priceString = is_scalar($price) ? (string) $price : '';

            if (! preg_match('/^\d+(\.\d+)?$/', $priceString)) {
                continue;
            }

            if (bccomp($priceString, '0', ProfitAndLossCalculator::SCALE) !== 1) {
                continue;
            }

            // Mantemos tudo em string decimal normalizada para cálculos com BCMath.
            $validatedPrices[$symbol] = bcadd($priceString, '0', ProfitAndLossCalculator::SCALE);
        }

        if ($validatedPrices === []) {
            throw new MarketDataResponseException('Nenhum preço USD válido foi retornado pela API de mercado.');
        }

        return $validatedPrices;
    }

    /**
     * Valida status e estrutura base para não corromper snapshots com dados inválidos.
     */
    private function ensureValidResponse(Response $response): void
    {
        $response->throw();

        if (! is_array($response->json()) || ! is_array(data_get($response->json(), 'data'))) {
            throw new MarketDataResponseException('Resposta de mercado inválida: payload não é um objeto JSON válido.');
        }
    }
}
