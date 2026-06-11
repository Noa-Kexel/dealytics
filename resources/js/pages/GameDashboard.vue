<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip

} from 'chart.js';
import type {ChartOptions} from 'chart.js';
import {
    Star,
    Bell,
    TrendingDown,
    Trophy,
    Calendar,
    Euro,
    Target,
    Plus,
    Trash2,
    Settings,
    ExternalLink,
    Flame,
    Check,
    TriangleAlert,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { Bar } from 'vue-chartjs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAlerts } from '@/composables/useAlerts';
import { useBudget  } from '@/composables/useBudget';
import type {Purchase} from '@/composables/useBudget';
import { useFavorites } from '@/composables/useFavorites';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip);

const { alerts, loadAlerts, getActiveAlerts, getReachedAlerts, removeAlert, checkAlerts } = useAlerts();
const {
    budget,
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
    getSpendingHistoryAsync,
} = useBudget();

const { favorites: favoritesData, loadFavorites } = useFavorites();
const favorites = computed(() => favoritesData.value);
const topDeals = ref<{ id: string; title: string; image: string | null; price: number | null; normalPrice: number | null; discount: number }[]>([]);
const loadingDeals = ref(true);

// Budget editing
const editingBudget = ref(false);
const newBudgetLimit = ref('');

// Add purchase form
const newPurchaseTitle = ref('');
const newPurchasePrice = ref('');
const newPurchaseOriginal = ref('');
const newPurchaseStore = ref('');
const justAddedOverBudget = ref(false);

const newPurchasePriceNum = computed(() => parseFloat(newPurchasePrice.value) || 0);

const purchaseWouldExceedBudget = computed(() =>
    wouldExceedBudgetWith(newPurchasePriceNum.value),
);

const projectedMonthlyTotal = computed(() => totalSpent.value + newPurchasePriceNum.value);

const purchaseOverflowAmount = computed(() =>
    getBudgetOverflowWith(newPurchasePriceNum.value),
);

// Spending chart
const spendingChartData = ref({
    labels: [] as string[],
    datasets: [
        {
            label: 'Dépenses (€)',
            data: [] as number[],
            backgroundColor: 'rgba(168, 85, 247, 0.7)',
            borderColor: '#A855F7',
            borderWidth: 1,
            borderRadius: 6,
        },
    ],
});

const spendingChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(6, 4, 15, 0.9)',
            borderColor: 'rgba(168, 85, 247, 0.3)',
            borderWidth: 1,
            titleColor: '#f2f2f2',
            bodyColor: '#f2f2f2',
            cornerRadius: 8,
            callbacks: {
                label: (tooltipItem) => {
                    const y = tooltipItem.parsed.y;

                    return y != null ? `${y.toFixed(2)}€` : '';
                },
            },
        },
    },
    scales: {
        x: {
            grid: { color: 'rgba(124, 58, 237, 0.08)' },
            ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 10 } },
        },
        y: {
            grid: { color: 'rgba(124, 58, 237, 0.08)' },
            ticks: {
                color: 'rgba(255,255,255,0.4)',
                font: { size: 10 },
                callback: (value: string | number) => `${value}€`,
            },
        },
    },
};

function saveBudgetLimit() {
    const val = parseFloat(newBudgetLimit.value);

    if (val > 0) {
        setLimit(val);
        editingBudget.value = false;
    }
}

async function submitPurchase() {
    const price = parseFloat(newPurchasePrice.value);
    const original = parseFloat(newPurchaseOriginal.value) || price;

    if (newPurchaseTitle.value && price > 0) {
        const willExceedBudget = wouldExceedBudgetWith(price);

        await addPurchase(newPurchaseTitle.value, price, original, newPurchaseStore.value || 'N/A');
        justAddedOverBudget.value = willExceedBudget;
        newPurchaseTitle.value = '';
        newPurchasePrice.value = '';
        newPurchaseOriginal.value = '';
        newPurchaseStore.value = '';
        refreshChart();
    }
}

async function handleRemovePurchase(id: string | number) {
    await removePurchase(id);
    refreshChart();
}

function formatPurchaseDate(purchase: Purchase): string {
    const raw = purchase.purchased_at ?? purchase.date;

    return raw ? new Date(raw).toLocaleDateString('fr-FR') : '';
}

function purchaseOriginalPrice(purchase: Purchase): number {
    return purchase.original_price ?? purchase.originalPrice ?? purchase.price;
}

async function refreshChart() {
    const history = await getSpendingHistoryAsync();
    spendingChartData.value = {
        labels: history.map((h) => h.month),
        datasets: [
            {
                label: 'Dépenses (€)',
                data: history.map((h) => h.spent),
                backgroundColor: 'rgba(168, 85, 247, 0.7)',
                borderColor: '#A855F7',
                borderWidth: 1,
                borderRadius: 6,
            },
        ],
    };
}

onMounted(async () => {
    // Load all data from DB (or localStorage for guests)
    await Promise.all([
        loadFavorites(),
        loadAlerts(),
        loadBudget(),
    ]);

    // Check alerts against current prices
    checkAlerts();

    // Load spending chart
    refreshChart();

    // Load top deals (Nexarda popularity feed, biggest discounts first)
    try {
        const response = await fetch('/api/games');
        const data = await response.json();
        topDeals.value = (data.games ?? [])
            .filter((g: { price: number | null; discount: number }) => g.price !== null && g.discount > 0)
            .sort((a: { discount: number }, b: { discount: number }) => b.discount - a.discount)
            .slice(0, 5);
    } catch {
        // ignore
    } finally {
        loadingDeals.value = false;
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Header -->
        <div class="mb-8 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-purple/20">
                <Trophy class="size-5 text-dealytics-purple" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-heading text-3xl font-bold text-foreground">Dashboard</h1>
                    <span class="rounded-full bg-dealytics-cyan/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-dealytics-cyan">
                        PRO
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">Votre centre de commande gaming</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Star class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-dealytics-purple">{{ favorites.length }}</div>
                <div class="text-xs text-muted-foreground">Jeux suivis</div>
                <div class="text-[10px] text-muted-foreground/70">dans la watchlist</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <Bell class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-foreground">{{ getActiveAlerts().length }}</div>
                <div class="text-xs text-muted-foreground">Alertes actives</div>
                <div class="text-[10px] text-muted-foreground/70">surveillance prix</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-dealytics-cyan">
                    {{ budget.purchases.length > 0 ? Math.round((totalSaved / (totalSpent + totalSaved)) * 100) + '%' : '--' }}
                </div>
                <div class="text-xs text-muted-foreground">Réduction moy.</div>
                <div class="text-[10px] text-muted-foreground/70">sur vos achats</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-pink/20">
                    <Euro class="size-4 text-dealytics-pink" />
                </div>
                <div class="text-2xl font-bold text-dealytics-pink">{{ totalSaved.toFixed(2) }}€</div>
                <div class="text-xs text-muted-foreground">Economisé</div>
                <div class="text-[10px] text-muted-foreground/70">ce mois-ci</div>
            </div>
        </div>

        <!-- Budget + Spending Chart -->
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <!-- Monthly Budget -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-dealytics-purple" />
                        <h2 class="font-heading text-lg font-semibold">Budget Mensuel</h2>
                    </div>
                    <span class="text-xs uppercase text-muted-foreground">
                        {{ new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) }}
                    </span>
                </div>

                <!-- Budget amount -->
                <div class="mb-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold" :class="isOverBudget ? 'text-red-400' : 'text-dealytics-cyan'">
                        {{ totalSpent.toFixed(2) }}€
                    </span>
                    <span class="text-sm text-muted-foreground">
                        / {{ budget.limit }}€
                        <button
                            class="ml-1 text-dealytics-purple hover:text-dealytics-purple/80"
                            @click="editingBudget = !editingBudget; newBudgetLimit = budget.limit.toString()"
                        >
                            <Settings class="inline size-3" />
                        </button>
                    </span>
                </div>

                <!-- Edit budget inline -->
                <div v-if="editingBudget" class="mb-3 flex gap-2">
                    <Input
                        v-model="newBudgetLimit"
                        type="number"
                        min="1"
                        placeholder="Nouveau budget"
                        class="h-8 text-sm"
                        @keyup.enter="saveBudgetLimit"
                    />
                    <Button size="sm" class="h-8 bg-dealytics-purple hover:bg-dealytics-deep-purple" @click="saveBudgetLimit">
                        <Check class="size-3.5" />
                    </Button>
                </div>

                <!-- Progress bar -->
                <div class="mb-2 h-2 overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="isOverBudget ? 'bg-red-400' : budgetPercent > 80 ? 'bg-dealytics-pink' : 'bg-dealytics-cyan'"
                        :style="{ width: `${Math.min(budgetPercent, 100)}%` }"
                    />
                </div>

                <p class="mb-4 text-xs text-muted-foreground">
                    <span v-if="isOverBudget" class="text-red-400">Budget dépassé de {{ (totalSpent - budget.limit).toFixed(2) }}€</span>
                    <span v-else>{{ 100 - budgetPercent }}% restant · {{ remaining.toFixed(2) }}€ disponible</span>
                </p>

                <!-- Mini stats -->
                <div class="mb-4 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold text-foreground">{{ budget.purchases.length }}</div>
                        <div class="text-[10px] text-muted-foreground">Achetés</div>
                    </div>
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold text-dealytics-cyan">{{ totalSaved.toFixed(2) }}€</div>
                        <div class="text-[10px] text-muted-foreground">Economisé</div>
                    </div>
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold" :class="isOverBudget ? 'text-red-400' : 'text-dealytics-pink'">
                            {{ remaining.toFixed(2) }}€
                        </div>
                        <div class="text-[10px] text-muted-foreground">Restant</div>
                    </div>
                </div>

                <!-- Add purchase -->
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm" class="w-full gap-2 text-xs">
                            <Plus class="size-3.5" />
                            Ajouter un achat
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="border-border/50 bg-background">
                        <DialogHeader>
                            <DialogTitle class="font-heading">Ajouter un achat</DialogTitle>
                        </DialogHeader>
                        <div class="space-y-3">
                            <Alert
                                v-if="purchaseWouldExceedBudget"
                                variant="destructive"
                                class="border-red-400/30 bg-red-400/10"
                            >
                                <TriangleAlert />
                                <AlertTitle>Budget mensuel dépassé</AlertTitle>
                                <AlertDescription>
                                    Avec cet achat, vous atteindrez {{ projectedMonthlyTotal.toFixed(2) }}€ ce mois-ci,
                                    soit {{ purchaseOverflowAmount.toFixed(2) }}€ au-dessus de votre budget de {{ budget.limit }}€.
                                    Vous vous êtes fixé une limite — ce n'est pas une bonne idée de la dépasser !
                                </AlertDescription>
                            </Alert>
                            <Input v-model="newPurchaseTitle" placeholder="Nom du jeu" class="text-sm" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="newPurchasePrice" type="number" step="0.01" min="0" placeholder="Prix payé (€)" class="text-sm" />
                                <Input v-model="newPurchaseOriginal" type="number" step="0.01" min="0" placeholder="Prix original (€)" class="text-sm" />
                            </div>
                            <Input v-model="newPurchaseStore" placeholder="Magasin (ex: Steam)" class="text-sm" />
                            <DialogClose as-child>
                                <Button
                                    class="w-full bg-dealytics-purple hover:bg-dealytics-deep-purple"
                                    :disabled="!newPurchaseTitle || !newPurchasePrice"
                                    @click="submitPurchase"
                                >
                                    Ajouter
                                </Button>
                            </DialogClose>
                        </div>
                    </DialogContent>
                </Dialog>

                <Alert
                    v-if="justAddedOverBudget && isOverBudget"
                    variant="destructive"
                    class="mt-3 border-red-400/30 bg-red-400/10"
                >
                    <TriangleAlert />
                    <AlertTitle>Budget dépassé</AlertTitle>
                    <AlertDescription class="flex flex-col gap-2">
                        <span>
                            Vous avez dépassé le budget mensuel que vous vous étiez fixé ({{ budget.limit }}€).
                            Total dépensé ce mois-ci : {{ totalSpent.toFixed(2) }}€.
                            Essayez de respecter vos objectifs la prochaine fois !
                        </span>
                        <button
                            type="button"
                            class="self-start text-xs font-medium underline underline-offset-2 hover:no-underline"
                            @click="justAddedOverBudget = false"
                        >
                            J'ai compris
                        </button>
                    </AlertDescription>
                </Alert>

                <!-- Purchase list -->
                <div v-if="budget.purchases.length > 0" class="mt-4 space-y-2">
                    <div
                        v-for="purchase in budget.purchases"
                        :key="purchase.id"
                        class="flex items-center justify-between rounded-lg bg-secondary/30 px-3 py-2"
                    >
                        <div>
                            <div class="text-xs font-medium">{{ purchase.gameTitle }}</div>
                            <div class="text-[10px] text-muted-foreground">
                                {{ purchase.store }} · {{ formatPurchaseDate(purchase) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <div class="text-xs font-semibold text-dealytics-cyan">{{ purchase.price.toFixed(2) }}€</div>
                                <div v-if="purchaseOriginalPrice(purchase) > purchase.price" class="text-[10px] text-muted-foreground line-through">
                                    {{ purchaseOriginalPrice(purchase).toFixed(2) }}€
                                </div>
                            </div>
                            <button
                                class="text-muted-foreground/50 hover:text-red-400"
                                @click="handleRemovePurchase(purchase.id)"
                            >
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spending History Chart -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center gap-2">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                    <h2 class="font-heading text-lg font-semibold">Historique Dépenses</h2>
                </div>
                <div class="h-56">
                    <Bar :data="spendingChartData" :options="spendingChartOptions" />
                </div>
            </div>
        </div>

        <!-- Alerts + Top Deals -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Active Alerts -->
            <div id="price-alerts" class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center gap-2">
                    <Bell class="size-4 text-dealytics-cyan" />
                    <h2 class="font-heading text-lg font-semibold">Alertes de prix</h2>
                </div>

                <!-- Reached alerts -->
                <div v-if="getReachedAlerts().length > 0" class="mb-4 space-y-2">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-dealytics-pink">Objectif atteint</p>
                    <div
                        v-for="alert in getReachedAlerts()"
                        :key="alert.game_id || alert.gameID"
                        class="flex items-center justify-between rounded-lg bg-dealytics-pink/10 px-3 py-2"
                    >
                        <div>
                            <div class="text-xs font-medium">{{ alert.title }}</div>
                            <div class="text-[10px] text-dealytics-pink">
                                Objectif {{ (alert.targetPrice ?? alert.target_price ?? 0).toFixed(2) }}€ atteint !
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/game/${alert.game_id || alert.gameID}`" class="text-dealytics-cyan hover:text-dealytics-cyan/80">
                                <ExternalLink class="size-3.5" />
                            </Link>
                            <button class="text-muted-foreground/50 hover:text-red-400" @click="removeAlert(alert.game_id || alert.gameID || '')">
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active alerts -->
                <div v-if="getActiveAlerts().length > 0" class="space-y-2">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">En surveillance</p>
                    <div
                        v-for="alert in getActiveAlerts()"
                        :key="alert.game_id || alert.gameID"
                        class="flex items-center justify-between rounded-lg bg-secondary/30 px-3 py-2"
                    >
                        <div>
                            <div class="text-xs font-medium">{{ alert.title }}</div>
                            <div class="text-[10px] text-muted-foreground">
                                Objectif : {{ (alert.targetPrice ?? alert.target_price ?? 0).toFixed(2) }}€
                                <span v-if="alert.currentPrice ?? alert.current_price">
                                    · Actuel : {{ (alert.currentPrice ?? alert.current_price ?? 0).toFixed(2) }}€
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/game/${alert.game_id || alert.gameID}`" class="text-dealytics-cyan hover:text-dealytics-cyan/80">
                                <ExternalLink class="size-3.5" />
                            </Link>
                            <button class="text-muted-foreground/50 hover:text-red-400" @click="removeAlert(alert.game_id || alert.gameID || '')">
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="alerts.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                    Aucune alerte. Ajoutez-en depuis une page de jeu.
                </p>
            </div>

            <!-- Top Deals -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center gap-2">
                    <Target class="size-4 text-dealytics-pink" />
                    <h2 class="font-heading text-lg font-semibold">Top Offres du Moment</h2>
                </div>

                <div v-if="loadingDeals" class="space-y-3">
                    <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-secondary/50" />
                </div>

                <div v-else class="space-y-2">
                    <Link
                        v-for="deal in topDeals"
                        :key="deal.id"
                        :href="`/game/${deal.id}`"
                        class="flex items-center gap-3 rounded-lg bg-secondary/30 p-2 transition-colors hover:bg-secondary/50"
                    >
                        <img :src="deal.image || ''" :alt="deal.title" class="size-10 rounded object-cover" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-medium">{{ deal.title }}</div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-dealytics-cyan">{{ (deal.price ?? 0).toFixed(2) }}€</span>
                                <span v-if="deal.normalPrice" class="text-[10px] text-muted-foreground line-through">{{ deal.normalPrice.toFixed(2) }}€</span>
                                <span class="flex items-center gap-0.5 text-[10px] font-semibold text-dealytics-pink">
                                    <Flame class="size-2.5" />
                                    -{{ Math.round(deal.discount) }}%
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
