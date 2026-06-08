import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { api } from '@/lib/api';

export interface PriceAlert {
    id?: number;
    game_id?: string;
    gameID?: string; // localStorage compat
    title: string;
    target_price?: number;
    targetPrice?: number; // localStorage compat
    current_price?: number | null;
    currentPrice?: number; // localStorage compat
    is_reached?: boolean;
    reached?: boolean; // localStorage compat
    created_at?: string;
    createdAt?: string; // localStorage compat
    notified_at?: string | null;
    notifiedAt?: string; // localStorage compat
}

// Normalize alert to a consistent shape
function normalize(a: PriceAlert): PriceAlert {
    return {
        ...a,
        game_id: a.game_id || a.gameID,
        gameID: a.game_id || a.gameID,
        target_price: a.target_price ?? a.targetPrice,
        targetPrice: a.target_price ?? a.targetPrice,
        current_price: a.current_price ?? a.currentPrice ?? null,
        currentPrice: a.current_price ?? a.currentPrice ?? undefined,
        is_reached: a.is_reached ?? a.reached ?? false,
        reached: a.is_reached ?? a.reached ?? false,
        created_at: a.created_at || a.createdAt,
        createdAt: a.created_at || a.createdAt,
        notified_at: a.notified_at || a.notifiedAt || null,
        notifiedAt: a.notified_at || a.notifiedAt || undefined,
    };
}

const STORAGE_KEY = 'dealytics_alerts';

const alerts = ref<PriceAlert[]>([]);
const loaded = ref(false);

function isAuthenticated(): boolean {
    try {
        const page = usePage();

        return !!(page.props as { auth?: { user?: unknown } })?.auth?.user;
    } catch {
        return false;
    }
}

// ── localStorage fallback ───────────────────────────────────

function loadFromStorage(): PriceAlert[] {
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

        return raw.map(normalize);
    } catch {
        return [];
    }
}

function saveToStorage() {
    const data = alerts.value.map((a) => ({
        gameID: a.game_id || a.gameID,
        title: a.title,
        targetPrice: a.target_price ?? a.targetPrice,
        currentPrice: a.current_price ?? a.currentPrice,
        reached: a.is_reached ?? a.reached ?? false,
        createdAt: a.created_at || a.createdAt,
        notifiedAt: a.notified_at || a.notifiedAt,
    }));

    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

// ── Public API ──────────────────────────────────────────────

export function useAlerts() {
    async function loadAlerts(): Promise<void> {
        if (isAuthenticated()) {
            try {
                const data = await api<PriceAlert[]>('/api/alerts');
                alerts.value = data.map(normalize);
            } catch {
                alerts.value = loadFromStorage();
            }
        } else {
            alerts.value = loadFromStorage();
        }

        loaded.value = true;
    }

    // Initialize if not yet loaded
    if (!loaded.value) {
        if (isAuthenticated()) {
            loadAlerts();
        } else {
            alerts.value = loadFromStorage();
            loaded.value = true;
        }
    }

    async function addAlert(gameID: string, title: string, targetPrice: number) {
        const existing = alerts.value.findIndex(
            (a) => (a.game_id || a.gameID) === gameID,
        );

        if (existing >= 0) {
            alerts.value[existing] = normalize({
                ...alerts.value[existing],
                target_price: targetPrice,
                is_reached: false,
                notified_at: null,
            });
        } else {
            alerts.value.push(normalize({
                game_id: gameID,
                gameID,
                title,
                target_price: targetPrice,
                is_reached: false,
                created_at: new Date().toISOString(),
            }));
        }

        if (isAuthenticated()) {
            try {
                await api('/api/alerts', {
                    method: 'POST',
                    body: { game_id: gameID, title, target_price: targetPrice },
                });
            } catch {
                // keep optimistic state
            }
        } else {
            saveToStorage();
        }
    }

    async function removeAlert(gameID: string) {
        alerts.value = alerts.value.filter(
            (a) => (a.game_id || a.gameID) !== gameID,
        );

        if (isAuthenticated()) {
            try {
                await api(`/api/alerts/${gameID}`, { method: 'DELETE' });
            } catch {
                // already removed optimistically
            }
        } else {
            saveToStorage();
        }
    }

    function getAlert(gameID: string): PriceAlert | undefined {
        return alerts.value.find(
            (a) => (a.game_id || a.gameID) === gameID,
        );
    }

    function getActiveAlerts(): PriceAlert[] {
        return alerts.value.filter(
            (a) => !(a.is_reached ?? a.reached),
        );
    }

    function getReachedAlerts(): PriceAlert[] {
        return alerts.value.filter(
            (a) => a.is_reached ?? a.reached,
        );
    }

    async function checkAlerts() {
        const active = getActiveAlerts();

        if (active.length === 0) {
            return;
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }

        for (const alert of active) {
            const alertGameId = alert.game_id || alert.gameID;

            try {
                const response = await fetch(
                    `https://www.cheapshark.com/api/1.0/games?id=${alertGameId}`,
                );
                const data = await response.json();

                if (!data?.deals?.length) {
                    continue;
                }

                const bestPrice = Math.min(
                    ...data.deals.map((d: { price: string }) => parseFloat(d.price)),
                );

                const idx = alerts.value.findIndex(
                    (a) => (a.game_id || a.gameID) === alertGameId,
                );

                if (idx >= 0) {
                    alerts.value[idx].current_price = bestPrice;
                    alerts.value[idx].currentPrice = bestPrice;
                }

                const targetPrice = alert.target_price ?? alert.targetPrice ?? 0;

                if (bestPrice <= targetPrice) {
                    if (idx >= 0) {
                        alerts.value[idx].is_reached = true;
                        alerts.value[idx].reached = true;
                        alerts.value[idx].notified_at = new Date().toISOString();
                        alerts.value[idx].notifiedAt = new Date().toISOString();
                    }

                    // Persist reached status to DB
                    if (isAuthenticated() && alertGameId) {
                        try {
                            await api(`/api/alerts/${alertGameId}`, {
                                method: 'PATCH',
                                body: {
                                    current_price: bestPrice,
                                    is_reached: true,
                                    notified_at: new Date().toISOString(),
                                },
                            });
                        } catch {
                            // already updated locally
                        }
                    }

                    // Browser notification
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Dealytics - Alerte prix atteint !', {
                            body: `${alert.title} est maintenant à $${bestPrice.toFixed(2)} (objectif: $${targetPrice.toFixed(2)})`,
                            icon: '/favicon.svg',
                        });
                    }
                }
            } catch {
                // Skip
            }
        }

        if (!isAuthenticated()) {
            saveToStorage();
        }
    }

    return {
        alerts,
        loadAlerts,
        addAlert,
        removeAlert,
        getAlert,
        getActiveAlerts,
        getReachedAlerts,
        checkAlerts,
    };
}
