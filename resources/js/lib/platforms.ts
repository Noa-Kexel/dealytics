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

/** Extrait le code plateforme depuis edition_full (FOR:…). */
export function extractOfferPlatform(editionFull?: string | null, platform?: string | null): string | null {
    if (platform) {
        return platform;
    }

    const match = editionFull?.match(/FOR:([A-Z0-9-]+)/i);

    return match ? match[1].toUpperCase() : null;
}
