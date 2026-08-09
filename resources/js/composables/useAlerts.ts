import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { api } from '@/lib/api';
import { fetchNexardaGame } from '@/lib/nexarda';

export interface PriceAlert {
    id?: number;
    game_id?: string;
    gameID?: string;
    title: string;
    target_price?: number;
    targetPrice?: number;
    current_price?: number | null;
    currentPrice?: number;
    is_reached?: boolean;
    reached?: boolean;
    created_at?: string;
    createdAt?: string;
    notified_at?: string | null;
    notifiedAt?: string;
}

export interface TriggeredAlert {
    game_id: string;
    title: string;
    target_price: number;
    current_price: number;
}

function toNumber(value: unknown): number | null {
    if (value == null || value === '') {
        return null;
    }

    const n = Number(value);

    return Number.isFinite(n) ? n : null;
}

function normalize(a: PriceAlert): PriceAlert {
    const target = toNumber(a.target_price ?? a.targetPrice) ?? 0;
    const current = toNumber(a.current_price ?? a.currentPrice);

    return {
        ...a,
        game_id: a.game_id || a.gameID,
        gameID: a.game_id || a.gameID,
        target_price: target,
        targetPrice: target,
        current_price: current,
        currentPrice: current ?? undefined,
        is_reached: a.is_reached ?? a.reached ?? false,
        reached: a.is_reached ?? a.reached ?? false,
        created_at: a.created_at || a.createdAt,
        createdAt: a.created_at || a.createdAt,
        notified_at: a.notified_at || a.notifiedAt || null,
        notifiedAt: a.notified_at || a.notifiedAt || undefined,
    };
}

const STORAGE_KEY = 'dealytics_alerts';
const POLL_INTERVAL_MS = 15 * 60 * 1000;

const alerts = ref<PriceAlert[]>([]);
const loaded = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let authenticated = false;

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

export async function requestNotificationPermission(): Promise<NotificationPermission | 'unsupported'> {
    if (!('Notification' in window)) {
        return 'unsupported';
    }

    if (Notification.permission === 'default') {
        return Notification.requestPermission();
    }

    return Notification.permission;
}

function dispatchTriggered(triggered: TriggeredAlert) {
    window.dispatchEvent(
        new CustomEvent('dealytics:alert-triggered', {
            detail: {
                gameId: triggered.game_id,
                title: triggered.title,
                currentPrice: triggered.current_price,
                targetPrice: triggered.target_price,
            },
        }),
    );
}

function showBrowserNotification(triggered: TriggeredAlert) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    new Notification('Dealytics : Alerte prix atteint !', {
        body: `${triggered.title} est maintenant à ${triggered.current_price.toFixed(2)}€ (objectif: ${triggered.target_price.toFixed(2)}€)`,
        icon: '/favicon.svg',
    });
}

function handleTriggeredAlerts(triggered: TriggeredAlert[]) {
    for (const item of triggered) {
        dispatchTriggered(item);
        showBrowserNotification(item);
    }
}

export function useAlerts() {
    const page = usePage();
    authenticated = !!(page.props as { auth?: { user?: unknown } })?.auth?.user;

    async function loadAlerts(): Promise<void> {
        if (authenticated) {
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

    if (!loaded.value) {
        if (authenticated) {
            loadAlerts();
        } else {
            alerts.value = loadFromStorage();
            loaded.value = true;
        }
    }

    async function addAlert(gameID: string, title: string, targetPrice: number) {
        await requestNotificationPermission();

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

        if (authenticated) {
            try {
                await api('/api/alerts', {
                    method: 'POST',
                    body: { game_id: gameID, title, target_price: targetPrice },
                });
            } catch {
                // garde l'état optimiste
            }
        } else {
            saveToStorage();
        }

        startAlertPolling();
    }

    async function removeAlert(gameID: string) {
        alerts.value = alerts.value.filter(
            (a) => (a.game_id || a.gameID) !== gameID,
        );

        if (authenticated) {
            try {
                await api(`/api/alerts/${gameID}`, { method: 'DELETE' });
            } catch {
                // déjà retiré en optimiste
            }
        } else {
            saveToStorage();
        }

        if (getActiveAlerts().length === 0) {
            stopAlertPolling();
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

    async function checkAlerts(): Promise<TriggeredAlert[]> {
        const active = getActiveAlerts();

        if (active.length === 0) {
            return [];
        }

        if (authenticated) {
            return checkAlertsServer();
        }

        return checkAlertsClient(active);
    }

    async function checkAlertsServer(): Promise<TriggeredAlert[]> {
        try {
            const data = await api<{
                alerts: PriceAlert[];
                triggered: TriggeredAlert[];
            }>('/api/alerts/check', { method: 'POST' });

            alerts.value = data.alerts.map(normalize);

            if (data.triggered.length > 0) {
                handleTriggeredAlerts(data.triggered);
                window.dispatchEvent(new CustomEvent('dealytics:notifications-changed'));
            }

            return data.triggered;
        } catch {
            return [];
        }
    }

    async function checkAlertsClient(active: PriceAlert[]): Promise<TriggeredAlert[]> {
        const triggered: TriggeredAlert[] = [];

        for (const alert of active) {
            if (alert.is_reached || alert.notified_at) {
                continue;
            }

            const alertGameId = alert.game_id || alert.gameID;

            try {
                const data = await fetchNexardaGame(alertGameId!);

                if (data?.lowest == null) {
                    continue;
                }

                const bestPrice = data.lowest;
                const idx = alerts.value.findIndex(
                    (a) => (a.game_id || a.gameID) === alertGameId,
                );

                if (idx >= 0) {
                    alerts.value[idx].current_price = bestPrice;
                    alerts.value[idx].currentPrice = bestPrice;
                }

                const targetPrice = alert.target_price ?? alert.targetPrice ?? 0;

                if (bestPrice <= targetPrice) {
                    const now = new Date().toISOString();

                    if (idx >= 0) {
                        alerts.value[idx].is_reached = true;
                        alerts.value[idx].reached = true;
                        alerts.value[idx].notified_at = now;
                        alerts.value[idx].notifiedAt = now;
                    }

                    triggered.push({
                        game_id: alertGameId!,
                        title: alert.title,
                        target_price: targetPrice,
                        current_price: bestPrice,
                    });
                }
            } catch {
                // ignore
            }
        }

        if (triggered.length > 0) {
            saveToStorage();
            handleTriggeredAlerts(triggered);
        }

        return triggered;
    }

    function startAlertPolling() {
        if (pollTimer || getActiveAlerts().length === 0) {
            return;
        }

        pollTimer = setInterval(() => {
            if (getActiveAlerts().length === 0) {
                stopAlertPolling();

                return;
            }

            checkAlerts();
        }, POLL_INTERVAL_MS);
    }

    function stopAlertPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
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
        startAlertPolling,
        stopAlertPolling,
        requestNotificationPermission,
    };
}
