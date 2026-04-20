<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePortfolioPositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_symbol' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,8})?$/'],
            'average_price_input' => ['required', 'regex:/^\d{1,10}(\.\d{1,8})?$/'],

            'input_currency' => ['required', 'string', 'in:BRL,USD'],
        ];
    }

    /**
     * Normaliza formato de entrada sem alterar significado financeiro.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Slug deve seguir o formato da API de mercado (ex.: BTC, SOL).
            'asset_symbol' => Str::upper((string) $this->input('asset_symbol')),
            'input_currency' => Str::upper((string) $this->input('input_currency')),
        ]);
    }
}
