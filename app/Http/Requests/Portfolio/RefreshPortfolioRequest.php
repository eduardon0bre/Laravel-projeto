<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RefreshPortfolioRequest extends FormRequest
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
            // Lista opcional permite refresh parcial por ativos específicos.
            'asset_symbols' => ['sometimes', 'array', 'min:1'],
            'asset_symbols.*' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * Padroniza símbolos para evitar duplicidade por variação de caixa.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('asset_symbols')) {
            return;
        }

        $symbols = collect((array) $this->input('asset_symbols'))
            ->map(fn (string $symbol): string => Str::upper($symbol))
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'asset_symbols' => $symbols,
        ]);
    }
}
