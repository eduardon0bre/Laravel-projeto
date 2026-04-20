<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\PortfolioPositionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioPositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PortfolioPositionData $position */
        $position = $this->resource;

        return $position->toArray();
    }
}
