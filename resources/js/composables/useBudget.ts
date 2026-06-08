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

// ── localStorage fallback ───────────────────────────────────

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
        // silently fail
    }
}

function loadAllMonthsFromStorage(): Record<string, MonthlyBudget> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch {
        return {};
    }
}

// ── Normalize purchase ──────────────────────────────────────

function normalizePurchase(p: Purchase): Purchase {
    return {
        ...p,
        game_title: p.game_title || p.gameTitle,
        gameTitle: p.game_title || p.gameTitle,
        original_price: p.original_price ?? p.originalPrice ?? p.price,
        originalPrice: p.original_price ?? p.originalPrice ?? p.price,
        purchased_at: p.purchased_at || p.date,
        date: p.purchased_at || p.date,
    };
}

// ── Public API ──────────────────────────────────────────────

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
            // Load budget limit + purchases in parallel
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
        budget.value.purchases.reduce((sum, p) => sum + p.price, 0),
    );

    const totalSaved = computed(() =>
        budget.value.purchases.reduce((sum, p) => {
            const original = p.original_price ?? p.originalPrice ?? p.price;

            return sum + (original - p.price);
        }, 0),
    );

    const remaining = computed(() => Math.max(0, budget.value.limit - totalSpent.value));

    const budgetPercent = computed(() =>
        budget.value.limit > 0
            ? Math.min(100, Math.round((totalSpent.value / budget.value.limit) * 100))
            : 0,
    );

    const isOverBudget = computed(() => totalSpent.value > budget.value.limit);

    async function setLimit(limit: number) {
        budget.value.limit = limit;

        if (isAuthenticated()) {
            try {
                await api('/api/budget', {
                    method: 'PUT',
                    body: { monthly_limit: limit },
                });
            } catch {
                // keep local value
            }
        } else {
            saveToStorage(budget.value);
        }
    }

    async function addPurchase(gameTitle: string, price: number, originalPrice: number, store: string) {
        const tempId = crypto.randomUUID();
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

                // Replace temp ID with real DB ID
                const idx = budget.value.purchases.findIndex((p) => p.id === tempId);

                if (idx >= 0) {
                    budget.value.purchases[idx] = normalizePurchase(created);
                }
            } catch {
                // keep optimistic state
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
                // already removed locally
            }
        } else {
            saveToStorage(budget.value);
        }
    }

    function getSpendingHistory(): { month: string; spent: number }[] {
        if (isAuthenticated()) {
            // Will be fetched async — return empty initially
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
        loadBudget,
        setLimit,
        addPurchase,
        removePurchase,
        getSpendingHistory,
        getSpendingHistoryAsync,
    };
}
