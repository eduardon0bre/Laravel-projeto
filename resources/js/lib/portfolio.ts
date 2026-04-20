export type PortfolioPosition = {
    id: number;
    asset_symbol: string;
    asset_title: string | null;
    quantity: string;
    average_price_input: string;
    input_currency: 'BRL' | 'USD';
    average_price_usd: string;
    current_price_usd: string | null;
    invested_value_usd: string | null;
    current_value_usd: string | null;
    profit_loss_usd: string | null;
    profit_percentage: string | null;
};

export type PortfolioSummary = {
    positions_count: number;
    invested_total_usd: string;
    current_total_usd: string;
    profit_loss_total_usd: string;
};

export type PortfolioResponse = {
    data: PortfolioPosition[];
    summary: PortfolioSummary;
};

export type AssetReference = {
    id: number;
    title: string;
    slug: string;
};

export type AssetReferenceResponse = {
    data: AssetReference[];
};

export type PortfolioRoute = {
    url: string;
    method: string;
};

type ApiErrorPayload = {
    message?: string;
    errors?: Record<string, string[] | string>;
};

export async function requestJson<T>(route: PortfolioRoute, payload?: unknown): Promise<T> {
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const headers: HeadersInit = {
        Accept: 'application/json',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    if (payload !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        headers,
        credentials: 'same-origin',
        body: payload === undefined ? undefined : JSON.stringify(payload),
    });

    const isJsonResponse = response.headers.get('content-type')?.includes('application/json') === true;
    const raw = isJsonResponse
        ? ((await response.json()) as T | ApiErrorPayload)
        : ({ message: await response.text() } as ApiErrorPayload);

    if (!response.ok) {
        const apiError = raw as ApiErrorPayload;
        const validationErrors = apiError.errors ?? {};
        const firstValidationError = Object.values(validationErrors)
            .flatMap((entry) => (Array.isArray(entry) ? entry : [entry]))
            .find((entry) => typeof entry === 'string');

        throw new Error(firstValidationError ?? apiError.message ?? 'Falha na requisição do portfólio.');
    }

    return raw as T;
}

export function normalizeDecimalToScale(rawInput: string, scale: number): string | null {
    const normalizedInput = rawInput.replaceAll(',', '.').trim();

    if (!/^\d{1,10}(\.\d{1,8})?$/.test(normalizedInput)) {
        return null;
    }

    const [integerPart, fractionalPart = ''] = normalizedInput.split('.');

    return `${integerPart}.${fractionalPart.padEnd(scale, '0').slice(0, scale)}`;
}

export function normalizeCryptoToEightDecimals(rawInput: string): string | null {
    return normalizeDecimalToScale(rawInput, 8);
}

export function formatBrlMask(rawInput: string): string {
    const digitsOnly = rawInput.replaceAll(/\D/g, '').slice(0, 12);

    if (digitsOnly.length === 0) {
        return '';
    }

    const padded = digitsOnly.padStart(3, '0');
    const integerPart = padded.slice(0, -2).replace(/^0+(?=\d)/, '');
    const decimalPart = padded.slice(-2);

    const integerWithThousands = (integerPart === '' ? '0' : integerPart).replaceAll(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `R$ ${integerWithThousands},${decimalPart}`;
}

export function formatUsdMask(rawInput: string): string {
    const digitsOnly = rawInput.replaceAll(/\D/g, '').slice(0, 12);

    if (digitsOnly.length === 0) {
        return '';
    }

    const padded = digitsOnly.padStart(3, '0');
    const integerPart = padded.slice(0, -2).replace(/^0+(?=\d)/, '');
    const decimalPart = padded.slice(-2);

    const integerWithThousands = (integerPart === '' ? '0' : integerPart).replaceAll(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$ ${integerWithThousands}.${decimalPart}`;
}

export function normalizeBrlMaskToCents(rawInput: string): string | null {
    return normalizeCurrencyMaskToCents(rawInput);
}

export function normalizeUsdMaskToCents(rawInput: string): string | null {
    return normalizeCurrencyMaskToCents(rawInput);
}

function normalizeCurrencyMaskToCents(rawInput: string): string | null {
    const digitsOnly = rawInput.replaceAll(/\D/g, '');

    if (digitsOnly.length === 0) {
        return null;
    }

    return digitsOnly;
}

export function centsToBrlMask(cents: string): string {
    return formatBrlMask(cents);
}

export function centsToUsdMask(cents: string): string {
    return formatUsdMask(cents);
}
