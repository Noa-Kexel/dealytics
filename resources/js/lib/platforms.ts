/** Slugs Nexarda (filtres / badges) → libellés UI. */
export const PLATFORM_NAMES: Record<string, string> = {
    steam: 'Steam',
    gog: 'GOG',
    'epic-games-launcher': 'Epic Games',
    xbox: 'Xbox',
    'xbox-play-anywhere': 'Xbox',
    playstation: 'PlayStation',
    nintendo: 'Nintendo',
    'ea-app': 'EA',
    'ubisoft-connect': 'Ubisoft',
    'battle-net': 'Battle.net',
};

/** Options de filtre affichées sur l'accueil. */
export const FILTER_PLATFORMS = [
    { slug: 'steam', name: 'Steam' },
    { slug: 'gog', name: 'GOG' },
    { slug: 'epic-games-launcher', name: 'Epic Games' },
    { slug: 'xbox', name: 'Xbox' },
    { slug: 'playstation', name: 'PlayStation' },
    { slug: 'nintendo', name: 'Nintendo' },
] as const;

/** Codes plateforme offres (FOR:PS5, etc.). */
export const OFFER_PLATFORM_LABELS: Record<string, string> = {
    WINDOWS: 'PC',
    MAC: 'Mac',
    LINUX: 'Linux',
    'XBOX-XS': 'Xbox Series',
    'XBOX-ONE': 'Xbox One',
    XBOX: 'Xbox',
    PS5: 'PlayStation 5',
    PS4: 'PlayStation 4',
    SWITCH: 'Nintendo Switch',
    'SWITCH-2': 'Nintendo Switch 2',
};

export function platformName(slug: string): string | undefined {
    return PLATFORM_NAMES[slug];
}

export function offerPlatformLabel(slug: string): string {
    return OFFER_PLATFORM_LABELS[slug] ?? slug.replace(/-/g, ' ');
}

const FOR_PLATFORM_RE = /FOR:\s*([A-Z0-9-]+)/gi;

/** Tous les codes plateforme présents dans edition_full (FOR:…). */
export function extractOfferPlatforms(
    editionFull?: string | null,
    platform?: string | null,
): string[] {
    const found: string[] = [];

    if (editionFull) {
        for (const match of editionFull.matchAll(FOR_PLATFORM_RE)) {
            const code = match[1].toUpperCase();

            if (!found.includes(code)) {
                found.push(code);
            }
        }
    }

    if (platform) {
        const code = platform.toUpperCase();

        if (!found.includes(code)) {
            found.unshift(code);
        }
    }

    return found;
}

/** Premier code plateforme (filtre / rétrocompat). */
export function extractOfferPlatform(editionFull?: string | null, platform?: string | null): string | null {
    return extractOfferPlatforms(editionFull, platform)[0] ?? null;
}

export type ParsedOfferEdition = {
    label: string | null;
    platforms: string[];
};

/** Sépare le libellé d'édition des tags FOR:… pour l'affichage. */
export function parseOfferEdition(
    editionFull?: string | null,
    edition?: string | null,
    platform?: string | null,
): ParsedOfferEdition {
    const platforms = extractOfferPlatforms(editionFull, platform);
    const cleaned = editionFull?.replace(FOR_PLATFORM_RE, '').replace(/\s+/g, ' ').trim() ?? '';
    const label = cleaned || edition?.trim() || null;

    return { label, platforms };
}
