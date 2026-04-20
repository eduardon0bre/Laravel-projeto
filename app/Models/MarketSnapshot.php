<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MarketSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'asset_symbol',
    'price_usd',
    'source',
    'captured_at',
])]
class MarketSnapshot extends Model
{
    /** @use HasFactory<MarketSnapshotFactory> */
    use HasFactory;

    /**
     * Casts garantem formato consistente e auditável do snapshot.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:8',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * Snapshot sempre pertence a um usuário para evitar cruzamento de estados.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
