import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {


    requestJson
} from '@/lib/portfolio';
import type { AssetReference, PortfolioResponse } from '@/lib/portfolio';
import { refresh as refreshPortfolio } from '@/routes/portfolio';

type MarketGridProps = {
    assetReferences: AssetReference[];
    initialPortfolioRows?: PortfolioResponse['data'];
};

type MarketRow = {
    symbol: string;
    title: string;
    currentPrice: string | null;
};

export function MarketGrid({ assetReferences, initialPortfolioRows = [] }: Readonly<MarketGridProps>) {
    const [rows, setRows] = useState<MarketRow[]>([]);
    const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [hasLoaded, setHasLoaded] = useState<boolean>(false);

    const titleBySlug = useMemo(
        () => new Map(assetReferences.map((asset) => [asset.slug, asset.title])),
        [assetReferences],
    );

    useEffect(() => {
        if (initialPortfolioRows.length === 0) {
            return;
        }

        setRows(initialPortfolioRows.map((position) => ({
            symbol: position.asset_symbol,
            title: titleBySlug.get(position.asset_symbol) ?? position.asset_title ?? position.asset_symbol,
            currentPrice: position.current_price_usd,
        })));
        setHasLoaded(true);
    }, [initialPortfolioRows, titleBySlug]);

    async function handleRefresh(): Promise<void> {
        setIsRefreshing(true);
        setErrorMessage(null);

        try {
            const response = await requestJson<PortfolioResponse>(refreshPortfolio());

            const mappedRows = response.data.map((position) => ({
                symbol: position.asset_symbol,
                title: titleBySlug.get(position.asset_symbol) ?? position.asset_title ?? position.asset_symbol,
                currentPrice: position.current_price_usd,
            }));

            setRows(mappedRows);
            setHasLoaded(true);
        } catch (error) {
            console.error('[MarketGrid][Refresh] Falha ao carregar dados de mercado via backend:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Falha inesperada ao atualizar mercado.');
        } finally {
            setIsRefreshing(false);
        }
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Market grid</CardTitle>
                    <CardDescription>Dados de mercado carregados via backend com atualização manual adicional.</CardDescription>
                </div>
                <Button type="button" onClick={handleRefresh} disabled={isRefreshing}>
                    {isRefreshing ? 'Atualizando...' : 'Refresh market'}
                </Button>
            </CardHeader>
            <CardContent className="grid gap-3">
                {errorMessage ? <p className="text-sm text-destructive">{errorMessage}</p> : null}

                {hasLoaded ? null : (
                    <p className="text-sm text-muted-foreground">
                        Execute "Refresh market" para carregar dados de mercado.
                    </p>
                )}

                {hasLoaded && rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nenhum preço retornado pelo backend.</p>
                ) : null}

                {rows.map((row) => (
                    <div key={row.symbol} className="grid gap-2 rounded-md border p-3 md:grid-cols-3">
                        <div>
                            <p className="text-xs text-muted-foreground">Asset name</p>
                            <p className="text-sm">{row.title}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Ticker</p>
                            <p className="text-sm">{row.symbol}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Current price</p>
                            <p className="text-sm">{row.currentPrice ?? '-'}</p>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
