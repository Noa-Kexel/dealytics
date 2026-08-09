export interface GameItem {
    id: string;
    title: string;
    image: string | null;
    price: number | null;
    normalPrice: number | null;
    discount: number;
    upcoming?: boolean;
    platforms?: string[];
}

export interface GamesResponse {
    games: GameItem[];
    page: number;
    pages: number;
    total: number;
}
