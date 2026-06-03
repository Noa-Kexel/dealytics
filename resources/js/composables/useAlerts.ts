import { ref } from 'vue';

export interface PriceAlert {
    gameID: string;
    title: string;
    targetPrice: number;
    currentPrice?: number;
    reached: boolean;
    createdAt: string;
    notifiedAt?: string;
}

const STORAGE_KEY = 'dealytics_alerts';

const alerts = ref<PriceAlert[]>(loadAlerts());

function loadAlerts(): PriceAlert[] {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch {
        return [];
    }
}

function saveAlerts() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(alerts.value));
}

export function useAlerts() {
    function addAlert(gameID: string, title: string, targetPrice: number) {
        const existing = alerts.value.findIndex((a) => a.gameID === gameID);

        if (existing >= 0) {
            alerts.value[existing].targetPrice = targetPrice;
            alerts.value[existing].reached = false;
            alerts.value[existing].notifiedAt = undefined;
        } else {
            alerts.value.push({
                gameID,
                title,
                targetPrice,
                reached: false,
                createdAt: new Date().toISOString(),
            });
        }

        saveAlerts();
    }

    function removeAlert(gameID: string) {
        alerts.value = alerts.value.filter((a) => a.gameID !== gameID);
        saveAlerts();
    }

    function getAlert(gameID: string): PriceAlert | undefined {
        return alerts.value.find((a) => a.gameID === gameID);
    }

    function getActiveAlerts(): PriceAlert[] {
        return alerts.value.filter((a) => !a.reached);
    }

    function getReachedAlerts(): PriceAlert[] {
        return alerts.value.filter((a) => a.reached);
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
            try {
                const response = await fetch(
                    `https://www.cheapshark.com/api/1.0/games?id=${alert.gameID}`,
                );
                const data = await response.json();

                if (!data?.deals?.length) {
                    continue;
                }

                const bestPrice = Math.min(
                    ...data.deals.map((d: { price: string }) => parseFloat(d.price)),
                );

                // Update current price
                const idx = alerts.value.findIndex((a) => a.gameID === alert.gameID);

                if (idx >= 0) {
                    alerts.value[idx].currentPrice = bestPrice;
                }

                if (bestPrice <= alert.targetPrice) {
                    // Mark as reached
                    if (idx >= 0) {
                        alerts.value[idx].reached = true;
                        alerts.value[idx].notifiedAt = new Date().toISOString();
                    }

                    // Send browser notification
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Dealytics - Alerte prix atteint !', {
                            body: `${alert.title} est maintenant à $${bestPrice.toFixed(2)} (objectif: $${alert.targetPrice.toFixed(2)})`,
                            icon: '/favicon.svg',
                        });
                    }
                }
            } catch {
                // Skip this alert on error
            }
        }

        saveAlerts();
    }

    return {
        alerts,
        addAlert,
        removeAlert,
        getAlert,
        getActiveAlerts,
        getReachedAlerts,
        checkAlerts,
    };
}
