<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Exceptions\Portfolio\ExchangeRateResponseException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * Busca a taxa BRL por 1 USD.
     *
     * Exemplo: retorno "5.00000000" significa que 1 USD = 5 BRL.
     */
    public function getBrlPerUsdRate(): string
    {
        $response = Http::acceptJson()
            ->connectTimeout((int) config('services.exchange_rate.connect_timeout', 5))
            ->timeout((int) config('services.exchange_rate.timeout', 10))
            ->retry([200, 400, 800])
            ->get((string) config('services.exchange_rate.url'), [
                'base' => 'USD',
                'symbols' => 'BRL',
            ]);

        $this->ensureValidResponse($response);

        $rate = Arr::get($response->json(), 'rates.BRL');

        $rateString = is_scalar($rate) ? (string) $rate : '';

        if (! preg_match('/^\d+(\.\d+)?$/', $rateString)) {
            throw new ExchangeRateResponseException('Taxa BRL inválida ou ausente na resposta da API.');
        }

        if (bccomp($rateString, '0', ProfitAndLossCalculator::SCALE) !== 1) {
            throw new ExchangeRateResponseException('Taxa BRL deve ser maior que zero.');
        }

        // bcadd normaliza escala sem introduzir float.
        return bcadd($rateString, '0', ProfitAndLossCalculator::SCALE);
    }

    /**
     * Validação explícita protege contra sobrescrita com payload malformado.
     */
    private function ensureValidResponse(Response $response): void
    {
        $response->throw();

        if (! is_array($response->json()) || ! array_key_exists('rates', $response->json())) {
            throw new ExchangeRateResponseException('Resposta de câmbio inválida: estrutura inesperada.');
        }
    }
}
