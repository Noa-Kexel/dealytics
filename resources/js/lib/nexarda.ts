import { api } from '@/lib/api';

export interface NexardaOffer {
    url: string | null;
    store: string;
    storeImage: string | null;
    storeType: string;
    official: boolean;
    edition: string | null;
    editionFull: string | null;
    platform: string | null;
    region: string | null;
    price: number;
    discount: number;
    coupon: { code: string; discount: number; priceWithout: number } | null;
}

export interface NexardaGamePrices {
    game: { id: number; name: string; cover: string | null };
    currency: string;
    currencySymbol: string;
    lowest: number | null;
    highest: number | null;
    maxDiscount: number;
    storeCount: number;
    offerCount: number;
    editions: string[];
    offers: NexardaOffer[];
}

/** Prix multi-boutiques pour un jeu Nexarda (null si indisponible). */
export async function fetchNexardaGame(
    id: string | number,
): Promise<NexardaGamePrices | null> {
    try {
        return await api<NexardaGamePrices>(`/api/nexarda/game/${id}`);
    } catch {
        return null;
    }
}
