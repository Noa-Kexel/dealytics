import { ref, computed } from 'vue';

export interface Purchase {
    id: string;
    gameTitle: string;
    price: number;
    originalPrice: number;
    store: string;
    date: string;
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

function loadBudget(): MonthlyBudget {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        const key = currentMonthKey();

        return data[key] || { limit: 150, purchases: [] };
    } catch {
        return { limit: 150, purchases: [] };
    }
}

function saveBudget(budget: MonthlyBudget) {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        data[currentMonthKey()] = budget;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
        // silently fail
    }
}

function loadAllMonths(): Record<string, MonthlyBudget> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch {
        return {};
    }
}

export function useBudget() {
    const budget = ref<MonthlyBudget>(loadBudget());

    const totalSpent = computed(() =>
        budget.value.purchases.reduce((sum, p) => sum + p.price, 0),
    );

    const totalSaved = computed(() =>
        budget.value.purchases.reduce((sum, p) => sum + (p.originalPrice - p.price), 0),
    );

    const remaining = computed(() => Math.max(0, budget.value.limit - totalSpent.value));

    const budgetPercent = computed(() =>
        budget.value.limit > 0
            ? Math.min(100, Math.round((totalSpent.value / budget.value.limit) * 100))
            : 0,
    );

    const isOverBudget = computed(() => totalSpent.value > budget.value.limit);

    function setLimit(limit: number) {
        budget.value.limit = limit;
        saveBudget(budget.value);
    }

    function addPurchase(gameTitle: string, price: number, originalPrice: number, store: string) {
        budget.value.purchases.push({
            id: crypto.randomUUID(),
            gameTitle,
            price,
            originalPrice,
            store,
            date: new Date().toISOString(),
        });
        saveBudget(budget.value);
    }

    function removePurchase(id: string) {
        budget.value.purchases = budget.value.purchases.filter((p) => p.id !== id);
        saveBudget(budget.value);
    }

    function getSpendingHistory(): { month: string; spent: number }[] {
        const allMonths = loadAllMonths();
        const history: { month: string; spent: number }[] = [];

        // Get last 6 months
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

    return {
        budget,
        totalSpent,
        totalSaved,
        remaining,
        budgetPercent,
        isOverBudget,
        setLimit,
        addPurchase,
        removePurchase,
        getSpendingHistory,
    };
}
