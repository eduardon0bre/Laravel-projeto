import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { MarketGrid } from '@/components/portfolio/market-grid';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { requestJson } from '@/lib/portfolio';
import type { AssetReference, AssetReferenceResponse, PortfolioResponse } from '@/lib/portfolio';
import { dashboard, manageAssets } from '@/routes';
import { assets as portfolioAssets, index as portfolioIndex } from '@/routes/portfolio';

export default function Dashboard() {
    const [portfolio, setPortfolio] = useState<PortfolioResponse | null>(null);
    const [assetReferences, setAssetReferences] = useState<AssetReference[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const loadAssetReferences = useCallback(async (): Promise<void> => {
        try {
            const route = portfolioAssets();
            const response = await requestJson<AssetReferenceResponse>(route);
            setAssetReferences(response.data);
        } catch (error) {
            console.error('[Dashboard][Assets] Erro ao carregar catálogo de ativos:', error);
        }
    }, []);

    const loadPortfolio = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        setErrorMessage(null);

        try {
            const response = await requestJson<PortfolioResponse>(portfolioIndex());

            setPortfolio(response);
        } catch (error) {
            console.error('[Dashboard][Load] Falha ao carregar snapshot:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Erro inesperado ao carregar dashboard.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadPortfolio();
        void loadAssetReferences();
    }, [loadAssetReferences, loadPortfolio]);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="grid gap-1">
                            <CardTitle>Resumo do portfólio</CardTitle>
                            <CardDescription>
                                Dashboard somente leitura com snapshot salvo no backend.
                            </CardDescription>
                        </div>
                        <Button type="button" asChild>
                            <Link href={manageAssets()}>Gerenciar ativos</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {errorMessage ? <p className="text-sm text-destructive">{errorMessage}</p> : null}

                        {isLoading ? <p className="text-sm text-muted-foreground">Carregando dashboard...</p> : null}

                        {portfolio ? (
                            <div className="grid gap-2 rounded-md border p-3 text-sm md:grid-cols-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">Current total (USD)</p>
                                    <p>{portfolio.summary.current_total_usd}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Profit/Loss total (USD)</p>
                                    <p>{portfolio.summary.profit_loss_total_usd}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Positions count</p>
                                    <p>{portfolio.summary.positions_count}</p>
                                </div>
                            </div>
                        ) : null}

                        {!isLoading && portfolio !== null && portfolio.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Portfólio vazio.</p>
                        ) : null}

                        {!isLoading && portfolio !== null
                            ? portfolio.data.map((position) => (
                                <div key={position.id} className="grid gap-2 rounded-md border p-3 md:grid-cols-6">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Asset</p>
                                        <p className="text-sm">{position.asset_title ?? position.asset_symbol}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Quantity</p>
                                        <p className="text-sm">{position.quantity}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Average price (USD)</p>
                                        <p className="text-sm">{position.average_price_usd}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Current value (USD)</p>
                                        <p className="text-sm">{position.current_value_usd ?? '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Profit/Loss (USD)</p>
                                        <p className="text-sm">{position.profit_loss_usd ?? '-'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Profit/Loss (%)</p>
                                        <p className="text-sm">
                                            {position.profit_percentage === null ? '-' : `${position.profit_percentage}%`}
                                        </p>
                                    </div>
                                </div>
                            ))
                            : null}
                    </CardContent>
                </Card>

                <MarketGrid assetReferences={assetReferences} initialPortfolioRows={portfolio?.data ?? []} />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
