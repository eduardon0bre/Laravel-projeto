<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PortfolioPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'asset_symbol',
    'quantity',
    'average_price_input',
    'input_currency',
    'average_price_usd',
    'exchange_rate_used',
])]
class PortfolioPosition extends Model
{
    /** @use HasFactory<PortfolioPositionFactory> */
    use HasFactory;

    /**
     * Faz cast em decimais para preservar precisão textual e evitar float.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'average_price_input' => 'decimal:8',
            'average_price_usd' => 'decimal:8',
            'exchange_rate_used' => 'decimal:8',
        ];
    }

    /**
     * Cada posição pertence a um usuário autenticado.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
