import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { api } from '@/lib/api';

export interface FavoriteGame {
    id?: number;
    game_id: string;
    title: string;
    thumb: string;
    created_at?: string;
    /** Enrichissement côté client (prix Nexarda). */
    currentPrice?: number;
    normalPrice?: number;
    savings?: number;
    loading?: boolean;
}

const STORAGE_KEY = 'dealytics_favorites';

// État partagé entre toutes les instances du composable.
const favorites = ref<FavoriteGame[]>([]);
const loaded = ref(false);

function isAuthenticated(): boolean {
    try {
        const page = usePage();

        return !!(page.props as { auth?: { user?: unknown } })?.auth?.user;
    } catch {
        return false;
    }
}

function loadFromStorage(): FavoriteGame[] {
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

        return raw.map((f: { gameID: string; title: string; thumb: string; addedAt: string }) => ({
            game_id: f.gameID,
            title: f.title,
            thumb: f.thumb,
            created_at: f.addedAt,
        }));
    } catch {
        return [];
    }
}

function saveToStorage(items: FavoriteGame[] = favorites.value) {
    const data = items.map((f) => ({
        gameID: f.game_id,
        title: f.title,
        thumb: f.thumb,
        addedAt: f.created_at || new Date().toISOString(),
    }));

    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

function clearStorage() {
    localStorage.removeItem(STORAGE_KEY);
}

/**
 * Pousse les favoris invité (localStorage) vers le compte connecté,
 * puis purge le stockage local des entrées migrées avec succès.
 */
async function migrateLocalFavorites(): Promise<boolean> {
    const local = loadFromStorage();

    if (local.length === 0) {
        return false;
    }

    const existingIds = new Set(favorites.value.map((f) => f.game_id));
    const remaining: FavoriteGame[] = [];
    let migrated = false;

    for (const fav of local) {
        if (existingIds.has(fav.game_id)) {
            migrated = true;
            continue;
        }

        try {
            await api('/api/favorites', {
                method: 'POST',
                body: {
                    game_id: fav.game_id,
                    title: fav.title,
                    thumb: fav.thumb || null,
                },
            });
            existingIds.add(fav.game_id);
            migrated = true;
        } catch {
            remaining.push(fav);
        }
    }

    if (remaining.length === 0) {
        clearStorage();
    } else {
        saveToStorage(remaining);
    }

    return migrated;
}

export function useFavorites() {
    async function loadFavorites(): Promise<void> {
        if (isAuthenticated()) {
            try {
                const data = await api<FavoriteGame[]>('/api/favorites');
                favorites.value = data;

                if (await migrateLocalFavorites()) {
                    favorites.value = await api<FavoriteGame[]>('/api/favorites');
                }
            } catch {
                favorites.value = loadFromStorage();
            }
        } else {
            favorites.value = loadFromStorage();
        }

        loaded.value = true;
    }

    function isFavorite(gameId: string): boolean {
        return favorites.value.some((f) => f.game_id === gameId);
    }

    const favoriteIds = computed(() => new Set(favorites.value.map((f) => f.game_id)));

    async function addFavorite(gameId: string, title: string, thumb: string): Promise<void> {
        if (!isFavorite(gameId)) {
            favorites.value.push({
                game_id: gameId,
                title,
                thumb,
                created_at: new Date().toISOString(),
            });
        }

        if (isAuthenticated()) {
            try {
                await api('/api/favorites', {
                    method: 'POST',
                    body: { game_id: gameId, title, thumb },
                });
            } catch {
                favorites.value = favorites.value.filter((f) => f.game_id !== gameId);
            }
        } else {
            saveToStorage();
        }
    }

    async function removeFavorite(gameId: string): Promise<void> {
        const backup = [...favorites.value];
        favorites.value = favorites.value.filter((f) => f.game_id !== gameId);

        if (isAuthenticated()) {
            try {
                await api(`/api/favorites/${gameId}`, { method: 'DELETE' });
            } catch {
                favorites.value = backup;
            }
        } else {
            saveToStorage();
        }
    }

    async function toggleFavorite(gameId: string, title: string, thumb: string): Promise<boolean> {
        if (isFavorite(gameId)) {
            await removeFavorite(gameId);

            return false;
        }

        await addFavorite(gameId, title, thumb);

        return true;
    }

    return {
        favorites,
        loaded,
        favoriteIds,
        loadFavorites,
        isFavorite,
        addFavorite,
        removeFavorite,
        toggleFavorite,
    };
}
