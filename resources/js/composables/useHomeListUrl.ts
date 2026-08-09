import { router } from '@inertiajs/vue3';

const HOME_URL_KEY = 'dealytics:homeUrl';

const VALID_MAX = new Set(['all', '5', '10', '15', '20', '30', '50']);
const VALID_SORT = new Set(['popular', 'price', 'savings', 'title']);

export type HomeListFilters = {
    q: string;
    platform: string;
    max: string;
    sort: string;
    sale: boolean;
};

export function parseHomeListQuery(
    search: string,
    allowedPlatforms: ReadonlySet<string>,
): HomeListFilters {
    const params = new URLSearchParams(
        search.startsWith('?') ? search : search ? `?${search}` : '',
    );

    const platform = params.get('platform') ?? 'all';
    const max = params.get('max') ?? 'all';
    const sort = params.get('sort') ?? 'popular';

    return {
        q: params.get('q')?.trim() ?? '',
        platform: platform === 'all' || allowedPlatforms.has(platform) ? platform : 'all',
        max: VALID_MAX.has(max) ? max : 'all',
        sort: VALID_SORT.has(sort) ? sort : 'popular',
        sale: params.get('sale') === '1',
    };
}

export function buildHomeListPath(filters: HomeListFilters): string {
    const params = new URLSearchParams();

    if (filters.q.trim()) {
        params.set('q', filters.q.trim());
    }

    if (filters.platform !== 'all') {
        params.set('platform', filters.platform);
    }

    if (filters.max !== 'all') {
        params.set('max', filters.max);
    }

    if (filters.sort !== 'popular') {
        params.set('sort', filters.sort);
    }

    if (filters.sale) {
        params.set('sale', '1');
    }

    const qs = params.toString();

    return qs ? `/?${qs}` : '/';
}

export function rememberHomeListUrl(path: string): void {
    if (typeof sessionStorage === 'undefined') {
        return;
    }

    if (!path.startsWith('/') || path.startsWith('//')) {
        return;
    }

    sessionStorage.setItem(HOME_URL_KEY, path);
}

export function getRememberedHomeListUrl(): string {
    if (typeof sessionStorage === 'undefined') {
        return '/';
    }

    const saved = sessionStorage.getItem(HOME_URL_KEY);

    if (!saved || !saved.startsWith('/') || saved.startsWith('//')) {
        return '/';
    }

    return saved;
}

/** Met à jour l'URL (sans requête serveur) pour conserver filtres au retour. */
export function syncHomeListUrl(filters: HomeListFilters): string {
    const next = buildHomeListPath(filters);

    rememberHomeListUrl(next);

    if (typeof window === 'undefined') {
        return next;
    }

    const current = `${window.location.pathname}${window.location.search}`;

    if (current === next) {
        return next;
    }

    router.replace({
        url: next,
        preserveState: true,
        preserveScroll: true,
    });

    return next;
}
