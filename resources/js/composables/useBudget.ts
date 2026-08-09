import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { api } from '@/lib/api';

export interface Purchase {
    id: string | number;
    game_title?: string;
    gameTitle?: string; // localStorage compat
    price: number;
    original_price?: number;
    originalPrice?: number; // localStorage compat
    store: string;
    purchased_at?: string;
    date?: string; // localStorage compat
}

export interface MonthlyBudget {
    limit: number;
    purchases: Purchase[];
}

const STORAGE_KEY = 'dealytics_budget';

function currentMonthKey(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function isAuthenticated(): boolean {
    try {
        const page = usePage();

        return !!(page.props as { auth?: { user?: unknown } })?.auth?.user;
    } catch {
        return false;
    }
}

function loadFromStorage(): MonthlyBudget {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        const key = currentMonthKey();

        return data[key] || { limit: 150, purchases: [] };
    } catch {
        return { limit: 150, purchases: [] };
    }
}

function saveToStorage(budget: MonthlyBudget) {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        data[currentMonthKey()] = budget;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
        // ignore
    }
}

function loadAllMonthsFromStorage(): Record<string, MonthlyBudget> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch {
        return {};
    }
}

/** Cast Laravel `decimal:2` → number (sinon concaténation au lieu d'addition). */
function normalizePurchase(p: Purchase): Purchase {
    const price = Number(p.price);
    const originalPrice = Number(p.original_price ?? p.originalPrice ?? p.price);

    return {
        ...p,
        price,
        game_title: p.game_title || p.gameTitle,
        gameTitle: p.game_title || p.gameTitle,
        original_price: originalPrice,
        originalPrice,
        purchased_at: p.purchased_at || p.date,
        date: p.purchased_at || p.date,
    };
}

export function useBudget() {
    const budget = ref<MonthlyBudget>(loadFromStorage());
    const loaded = ref(false);

    async function loadBudget(): Promise<void> {
        if (!isAuthenticated()) {
            budget.value = loadFromStorage();
            loaded.value = true;

            return;
        }

        try {
            const [budgetData, purchasesData] = await Promise.all([
                api<{ monthly_limit: number }>('/api/budget'),
                api<Purchase[]>('/api/purchases'),
            ]);

            budget.value = {
                limit: budgetData.monthly_limit,
                purchases: purchasesData.map(normalizePurchase),
            };
        } catch {
            budget.value = loadFromStorage();
        }

        loaded.value = true;
    }

    const totalSpent = computed(() =>
        budget.value.purchases.reduce((sum, p) => sum + Number(p.price), 0),
    );

    const totalSaved = computed(() =>
        budget.value.purchases.reduce((sum, p) => {
            const original = Number(p.original_price ?? p.originalPrice ?? p.price);

            return sum + (original - Number(p.price));
        }, 0),
    );

    const remaining = computed(() => Math.max(0, budget.value.limit - totalSpent.value));

    const budgetPercent = computed(() =>
        budget.value.limit > 0
            ? Math.min(100, Math.round((totalSpent.value / budget.value.limit) * 100))
            : 0,
    );

    const isOverBudget = computed(() => totalSpent.value > budget.value.limit);

    function wouldExceedBudgetWith(additionalAmount: number): boolean {
        if (additionalAmount <= 0) {
            return false;
        }

        return totalSpent.value + additionalAmount > budget.value.limit;
    }

    function getBudgetOverflowWith(additionalAmount: number): number {
        return Math.max(0, totalSpent.value + additionalAmount - budget.value.limit);
    }

    async function setLimit(limit: number) {
        budget.value.limit = limit;

        if (isAuthenticated()) {
            try {
                await api('/api/budget', {
                    method: 'PUT',
                    body: { monthly_limit: limit },
                });
            } catch {
                // garde la valeur locale
            }
        } else {
            saveToStorage(budget.value);
        }
    }

    async function addPurchase(gameTitle: string, price: number, originalPrice: number, store: string) {
        // crypto.randomUUID() n'existe qu'en contexte sécurisé (HTTPS/localhost),
        // pas sur http://dealytics.test — un id temporaire maison suffit ici.
        const tempId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const now = new Date().toISOString();

        const purchase: Purchase = normalizePurchase({
            id: tempId,
            game_title: gameTitle,
            gameTitle,
            price,
            original_price: originalPrice,
            originalPrice,
            store,
            purchased_at: now,
            date: now,
        });

        budget.value.purchases.push(purchase);

        if (isAuthenticated()) {
            try {
                const created = await api<Purchase>('/api/purchases', {
                    method: 'POST',
                    body: {
                        game_title: gameTitle,
                        price,
                        original_price: originalPrice,
                        store,
                    },
                });

                // Remplace l'id temporaire par celui de la DB.
                const idx = budget.value.purchases.findIndex((p) => p.id === tempId);

                if (idx >= 0) {
                    budget.value.purchases[idx] = normalizePurchase(created);
                }
            } catch {
                // garde l'état optimiste
            }
        } else {
            saveToStorage(budget.value);
        }
    }

    async function removePurchase(id: string | number) {
        budget.value.purchases = budget.value.purchases.filter((p) => p.id !== id);

        if (isAuthenticated()) {
            try {
                await api(`/api/purchases/${id}`, { method: 'DELETE' });
            } catch {
                // déjà retiré localement
            }
        } else {
            saveToStorage(budget.value);
        }
    }

    function getSpendingHistory(): { month: string; spent: number }[] {
        if (isAuthenticated()) {
            return [];
        }

        const allMonths = loadAllMonthsFromStorage();
        const history: { month: string; spent: number }[] = [];
        const now = new Date();

        for (let i = 5; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const monthData = allMonths[key];
            const spent = monthData
                ? monthData.purchases.reduce((sum: number, p: Purchase) => sum + p.price, 0)
                : 0;

            history.push({
                month: d.toLocaleDateString('fr-FR', { month: 'short' }),
                spent,
            });
        }

        return history;
    }

    async function getSpendingHistoryAsync(): Promise<{ month: string; spent: number }[]> {
        if (isAuthenticated()) {
            try {
                return await api<{ month: string; spent: number }[]>('/api/purchases/history');
            } catch {
                return getSpendingHistory();
            }
        }

        return getSpendingHistory();
    }

    return {
        budget,
        loaded,
        totalSpent,
        totalSaved,
        remaining,
        budgetPercent,
        isOverBudget,
        wouldExceedBudgetWith,
        getBudgetOverflowWith,
        loadBudget,
        setLimit,
        addPurchase,
        removePurchase,
        getSpendingHistory,
        getSpendingHistoryAsync,
    };
}
