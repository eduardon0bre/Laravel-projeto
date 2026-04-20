import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {




    centsToBrlMask,
    centsToUsdMask,
    formatBrlMask,
    formatUsdMask,
    normalizeBrlMaskToCents,
    normalizeCryptoToEightDecimals,
    normalizeUsdMaskToCents,
    requestJson
} from '@/lib/portfolio';
import type { AssetReference, AssetReferenceResponse, PortfolioPosition, PortfolioResponse } from '@/lib/portfolio';
import { dashboard, manageAssets } from '@/routes';
import {
    assets as portfolioAssets,
    destroy as destroyPortfolio,
    index as portfolioIndex,
    store as storePortfolio,
} from '@/routes/portfolio';

function formatAverageInputDisplay(position: PortfolioPosition): string {
    if (position.input_currency === 'BRL') {
        return centsToBrlMask(position.average_price_input);
    }

    return centsToUsdMask(position.average_price_input);
}

export default function ManageAssets() {
    const [portfolio, setPortfolio] = useState<PortfolioResponse | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [isDeleteProcessing, setIsDeleteProcessing] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [assetReferences, setAssetReferences] = useState<AssetReference[]>([]);

    const [assetSymbol, setAssetSymbol] = useState<string>('');
    const [quantityInput, setQuantityInput] = useState<string>('');
    const [averagePriceInput, setAveragePriceInput] = useState<string>('');
    const [inputCurrency, setInputCurrency] = useState<'BRL' | 'USD'>('USD');
    const [editingPositionId, setEditingPositionId] = useState<number | null>(null);
    const [pendingDeletePositionId, setPendingDeletePositionId] = useState<number | null>(null);

    let submitLabel = 'Salvar edição';

    if (isSubmitting) {
        submitLabel = 'Salvando...';
    } else if (editingPositionId === null) {
        submitLabel = 'Adicionar ativo';
    }

    const loadAssetReferences = useCallback(async (): Promise<void> => {
        try {
            const response = await requestJson<AssetReferenceResponse>(portfolioAssets());

            setAssetReferences(response.data);

            setAssetSymbol((currentSymbol) => {
                if (currentSymbol !== '' || response.data.length === 0) {
                    return currentSymbol;
                }

                return response.data[0].slug;
            });
        } catch (error) {
            console.error('[ManageAssets][Assets] Erro ao carregar ativos:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Erro ao carregar catálogo de ativos.');
        }
    }, []);

    const loadPortfolio = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        setErrorMessage(null);

        try {
            const route = portfolioIndex();
            const response = await requestJson<PortfolioResponse>(route);
            setPortfolio(response);
        } catch (error) {
            console.error('[ManageAssets][Load] Erro ao carregar snapshot:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Erro ao carregar portfólio.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadAssetReferences();
        void loadPortfolio();
    }, [loadAssetReferences, loadPortfolio]);

    function resetForm(): void {
        setEditingPositionId(null);
        setQuantityInput('');
        setAveragePriceInput('');
        setInputCurrency('USD');
    }

    async function handleSubmit(event: { preventDefault: () => void }): Promise<void> {
        event.preventDefault();
        setIsSubmitting(true);
        setErrorMessage(null);

        const normalizedQuantity = normalizeCryptoToEightDecimals(quantityInput);
        const normalizedPrice = inputCurrency === 'BRL'
            ? normalizeBrlMaskToCents(averagePriceInput)
            : normalizeUsdMaskToCents(averagePriceInput);

        if (normalizedQuantity === null || normalizedPrice === null) {
            setErrorMessage('Formato inválido. Revise quantidade e preço antes de enviar.');
            setIsSubmitting(false);

            return;
        }

        const payload = {
            asset_symbol: assetSymbol,
            quantity: normalizedQuantity,
            average_price_input: normalizedPrice,
            input_currency: inputCurrency,
        };

        try {
            const route = storePortfolio();
            const response = await requestJson<PortfolioResponse>(route, payload);

            setPortfolio(response);
            resetForm();
        } catch (error) {
            console.error('[ManageAssets][Form] Erro de backend ao salvar ativo:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Erro inesperado ao salvar ativo.');
        } finally {
            setIsSubmitting(false);
        }
    }

    async function handleDelete(positionId: number): Promise<void> {
        setErrorMessage(null);
        setIsDeleteProcessing(true);

        try {
            const route = destroyPortfolio(positionId);
            const response = await requestJson<PortfolioResponse>(route);

            setPortfolio(response);

            if (editingPositionId === positionId) {
                resetForm();
            }

            setPendingDeletePositionId(null);
        } catch (error) {
            console.error('[ManageAssets][Delete] Erro ao remover ativo:', error);
            setErrorMessage(error instanceof Error ? error.message : 'Erro ao remover ativo.');
        } finally {
            setIsDeleteProcessing(false);
        }
    }

    function requestDeleteConfirmation(positionId: number): void {
        setPendingDeletePositionId(positionId);
    }

    function handleEdit(positionId: number): void {
        const selectedPosition = portfolio?.data.find((position) => position.id === positionId);

        if (!selectedPosition) {
            setErrorMessage('Ativo selecionado não foi encontrado para edição.');

            return;
        }

        setEditingPositionId(positionId);
        setAssetSymbol(selectedPosition.asset_symbol);
        setQuantityInput(selectedPosition.quantity);
        setInputCurrency(selectedPosition.input_currency);

        if (selectedPosition.input_currency === 'BRL') {
            setAveragePriceInput(centsToBrlMask(selectedPosition.average_price_input));
        } else {
            setAveragePriceInput(centsToUsdMask(selectedPosition.average_price_input));
        }
    }

    function handleAveragePriceChange(value: string): void {
        if (inputCurrency === 'BRL') {
            setAveragePriceInput(formatBrlMask(value));

            return;
        }

        setAveragePriceInput(formatUsdMask(value));
    }

    return (
        <>
            <Head title="Manage assets" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{editingPositionId === null ? 'Adicionar ativo' : 'Editar ativo'}</CardTitle>
                        <CardDescription>
                            Máscaras e normalização ocorrem somente no frontend. Validação permanece no backend.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-3 md:grid-cols-4" onSubmit={handleSubmit}>
                            <div className="grid gap-1.5">
                                <Label>Asset</Label>
                                <Select value={assetSymbol} onValueChange={setAssetSymbol}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Selecione um ativo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assetReferences.map((asset) => (
                                            <SelectItem key={asset.id} value={asset.slug}>
                                                {asset.title} ({asset.slug})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="quantity">Quantity</Label>
                                <Input
                                    id="quantity"
                                    value={quantityInput}
                                    onChange={(event) => setQuantityInput(event.target.value)}
                                    placeholder="1 ou 0.5"
                                    required
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="average_price_input">Average price</Label>
                                <Input
                                    id="average_price_input"
                                    value={averagePriceInput}
                                    onChange={(event) => handleAveragePriceChange(event.target.value)}
                                    placeholder={inputCurrency === 'BRL' ? 'R$ 1.500,00' : '$ 1,500.00'}
                                    required
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Currency</Label>
                                <ToggleGroup
                                    type="single"
                                    value={inputCurrency}
                                    onValueChange={(value) => {
                                        if (value !== 'BRL' && value !== 'USD') {
                                            return;
                                        }

                                        setInputCurrency(value);
                                        setAveragePriceInput('');
                                    }}
                                    variant="outline"
                                    className="w-full"
                                >
                                    <ToggleGroupItem value="BRL" className="flex-1">
                                        Real (BRL)
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="USD" className="flex-1">
                                        Dólar (USD)
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>

                            <div className="flex gap-2 md:col-span-4">
                                <Button type="submit" disabled={isSubmitting || assetReferences.length === 0}>
                                    {submitLabel}
                                </Button>

                                {editingPositionId === null ? null : (
                                    <Button type="button" variant="outline" onClick={resetForm}>
                                        Cancelar edição
                                    </Button>
                                )}

                                <Button type="button" variant="ghost" asChild>
                                    <Link href={dashboard()}>Voltar ao dashboard</Link>
                                </Button>
                            </div>
                        </form>

                        {errorMessage ? <p className="mt-3 text-sm text-destructive">{errorMessage}</p> : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Posições para gestão</CardTitle>
                        <CardDescription>Edição e remoção de ativos ficam isoladas nesta tela.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {isLoading ? <p className="text-sm text-muted-foreground">Carregando posições...</p> : null}

                        {!isLoading && portfolio !== null && portfolio.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Portfólio vazio.</p>
                        ) : null}

                        {!isLoading && portfolio !== null
                            ? portfolio.data.map((position) => (
                                <div key={position.id} className="grid gap-2 rounded-md border p-3 md:grid-cols-7">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Asset</p>
                                        <p className="text-sm">{position.asset_title ?? position.asset_symbol}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Quantity</p>
                                        <p className="text-sm">{position.quantity}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Average input</p>
                                        <p className="text-sm">{formatAverageInputDisplay(position)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Input currency</p>
                                        <p className="text-sm">{position.input_currency}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Average USD</p>
                                        <p className="text-sm">{position.average_price_usd}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">P/L (USD)</p>
                                        <p className="text-sm">{position.profit_loss_usd ?? '-'}</p>
                                    </div>
                                    <div className="flex items-end justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button type="button" variant="ghost" size="icon" aria-label="Ações">
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem onClick={() => handleEdit(position.id)}>
                                                    Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onClick={() => requestDeleteConfirmation(position.id)}
                                                >
                                                    Excluir
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            ))
                            : null}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={pendingDeletePositionId !== null} onOpenChange={(open) => !open && setPendingDeletePositionId(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar exclusão do ativo</DialogTitle>
                        <DialogDescription>
                            Esta ação é irreversível. O ativo e seus dados associados serão removidos definitivamente.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setPendingDeletePositionId(null)}
                            disabled={isDeleteProcessing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (pendingDeletePositionId === null) {
                                    return;
                                }

                                void handleDelete(pendingDeletePositionId);
                            }}
                            disabled={isDeleteProcessing || pendingDeletePositionId === null}
                        >
                            {isDeleteProcessing ? 'Excluindo...' : 'Confirmar exclusão'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ManageAssets.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Manage assets',
            href: manageAssets(),
        },
    ],
};
