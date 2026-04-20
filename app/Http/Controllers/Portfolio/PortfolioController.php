<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portfolio;

use App\Exceptions\Portfolio\ExchangeRateResponseException;
use App\Exceptions\Portfolio\MarketDataResponseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\RefreshPortfolioRequest;
use App\Http\Requests\Portfolio\StorePortfolioPositionRequest;
use App\Http\Resources\PortfolioPositionResource;
use App\Models\Asset;
use App\Models\PortfolioPosition;
use App\Models\User;
use App\Services\Portfolio\PortfolioService;
use Illuminate\Http\JsonResponse;

class PortfolioController extends Controller
{
    /**
     * Controlador fino: delega toda regra financeira para o service.
     */
    public function __construct(private readonly PortfolioService $portfolioService) {}

    /**
     * Retorna estado do portfólio usando apenas snapshot salvo (sem chamadas externas).
     */
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $result = $this->portfolioService->list($user);

        return response()->json([
            'data' => PortfolioPositionResource::collection($result['positions']),
            'summary' => $result['summary']->toArray(),
        ]);
    }

    /**
     * Retorna catálogo de ativos para seleção no frontend.
     */
    public function assets(): JsonResponse
    {
        $assets = Asset::query()
            ->orderBy('name')
            ->get(['id', 'name', 'ticker'])
            ->map(fn(Asset $asset): array => [
                'id' => $asset->id,
                'title' => (string) $asset->name,
                'slug' => strtoupper((string) $asset->ticker),
            ]);

        return response()->json([
            'data' => $assets,
        ]);
    }

    /**
     * Cria/atualiza posição financeira após normalização obrigatória para USD.
     */
    public function store(StorePortfolioPositionRequest $request): JsonResponse
    {
        try {
            $this->portfolioService->store(
                user: $request->user(),
                payload: $request->validated(),
            );
        } catch (ExchangeRateResponseException $exception) {
            return response()->json([
                'message' => 'Não foi possível converter BRL para USD no momento.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $result = $this->portfolioService->list($request->user());

        return response()->json([
            'data' => PortfolioPositionResource::collection($result['positions']),
            'summary' => $result['summary']->toArray(),
        ], 201);
    }

    /**
     * Atualiza snapshots de mercado somente por ação explícita do usuário.
     */
    public function refresh(RefreshPortfolioRequest $request): JsonResponse
    {
        try {
            $this->portfolioService->refresh(
                user: $request->user(),
                assetSymbols: $request->validated('asset_symbols'),
            );
        } catch (MarketDataResponseException $exception) {
            return response()->json([
                'message' => 'Não foi possível atualizar os preços de mercado.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $result = $this->portfolioService->list($request->user());

        return response()->json([
            'data' => PortfolioPositionResource::collection($result['positions']),
            'summary' => $result['summary']->toArray(),
        ]);
    }

    /**
     * Remove posição e snapshot correlato em transação para evitar estado parcial.
     */
    public function destroy(PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== auth()->id(), 403);

        /** @var User $user */
        $user = request()->user();

        $this->portfolioService->delete(
            user: $user,
            position: $portfolioPosition,
        );

        $result = $this->portfolioService->list($user);

        return response()->json([
            'data' => PortfolioPositionResource::collection($result['positions']),
            'summary' => $result['summary']->toArray(),
        ]);
    }
}
