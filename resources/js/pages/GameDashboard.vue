<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
    ArrowRight,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { Bar } from 'vue-chartjs';
import StatCard from '@/components/StatCard.vue';
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
import { buildHomeListPath } from '@/composables/useHomeListUrl';
import { vReveal } from '@/directives/reveal';
import type { GameItem } from '@/types';

const topOffersHomeUrl = buildHomeListPath(
    {
        q: '',
        platform: 'all',
        max: 'all',
        sort: 'savings',
        sale: true,
    },
    { to: 'deals' },
);

function goToTopOffers() {
    router.visit(topOffersHomeUrl, {
        onFinish: () => {
            const delays = [0, 50, 100, 250, 500];

            for (const delay of delays) {
                window.setTimeout(() => {
                    document.getElementById('deals')?.scrollIntoView({ behavior: 'auto', block: 'start' });
                }, delay);
            }
        },
    });
}

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
const topDeals = ref<GameItem[]>([]);
const loadingDeals = ref(true);

const editingBudget = ref(false);
const newBudgetLimit = ref('');

const newPurchaseTitle = ref('');
const newPurchasePrice = ref('');
const newPurchaseOriginal = ref('');
const newPurchaseStore = ref('');
const newPurchaseDate = ref(todayDateInputValue());
const justAddedOverBudget = ref(false);

function todayDateInputValue(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function isSameMonthAsCurrent(dateInput: string): boolean {
    const now = new Date();
    const [y, m] = dateInput.split('-').map(Number);

    return y === now.getFullYear() && m === now.getMonth() + 1;
}

const newPurchasePriceNum = computed(() => parseFloat(newPurchasePrice.value) || 0);

const purchaseAffectsCurrentMonth = computed(() =>
    isSameMonthAsCurrent(newPurchaseDate.value || todayDateInputValue()),
);

const purchaseWouldExceedBudget = computed(() =>
    purchaseAffectsCurrentMonth.value && wouldExceedBudgetWith(newPurchasePriceNum.value),
);

const projectedMonthlyTotal = computed(() => totalSpent.value + newPurchasePriceNum.value);

const purchaseOverflowAmount = computed(() =>
    getBudgetOverflowWith(newPurchasePriceNum.value),
);

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
    const purchaseDate = newPurchaseDate.value || todayDateInputValue();

    if (newPurchaseTitle.value && price > 0) {
        const willExceedBudget =
            isSameMonthAsCurrent(purchaseDate) && wouldExceedBudgetWith(price);

        await addPurchase(
            newPurchaseTitle.value,
            price,
            original,
            newPurchaseStore.value || 'N/A',
            purchaseDate,
        );
        justAddedOverBudget.value = willExceedBudget;
        newPurchaseTitle.value = '';
        newPurchasePrice.value = '';
        newPurchaseOriginal.value = '';
        newPurchaseStore.value = '';
        newPurchaseDate.value = todayDateInputValue();
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
    await Promise.all([
        loadFavorites(),
        loadAlerts(),
        loadBudget(),
    ]);

    checkAlerts();
    refreshChart();

    if (typeof window !== 'undefined' && window.location.hash) {
        requestAnimationFrame(() => {
            document.getElementById(window.location.hash.slice(1))
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    try {
        const response = await fetch('/api/games');
        const data = await response.json();
        topDeals.value = (data.games ?? [])
            .filter((g: GameItem) => g.price !== null && g.discount > 0)
            .sort((a: GameItem, b: GameItem) => b.discount - a.discount)
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
        <div class="mb-8 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-purple/20">
                <Trophy class="size-5 text-dealytics-purple" />
            </div>
            <div>
                <h1 class="font-heading text-3xl font-bold text-foreground">Dashboard</h1>
                <p class="text-sm text-muted-foreground">Votre centre de commande gaming</p>
            </div>
        </div>
        <div v-reveal="{ y: 16 }" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <StatCard
                :icon="Star"
                label="Jeux suivis"
                :value="favorites.length"
                hint="dans la watchlist"
                icon-class="text-dealytics-purple"
                icon-bg-class="bg-dealytics-purple/20"
                value-class="text-dealytics-purple"
            />
            <StatCard
                :icon="Bell"
                label="Alertes actives"
                :value="getActiveAlerts().length"
                hint="surveillance prix"
                icon-class="text-dealytics-cyan"
                icon-bg-class="bg-dealytics-cyan/20"
            />
            <StatCard
                :icon="TrendingDown"
                label="Réduction moy."
                :value="budget.purchases.length > 0 ? Math.round((totalSaved / (totalSpent + totalSaved)) * 100) + '%' : '--'"
                hint="sur vos achats"
                icon-class="text-dealytics-cyan"
                icon-bg-class="bg-dealytics-cyan/20"
                value-class="text-dealytics-cyan"
            />
            <StatCard
                :icon="Euro"
                label="Economisé"
                :value="`${totalSaved.toFixed(2)}€`"
                hint="ce mois-ci"
                icon-class="text-dealytics-pink"
                icon-bg-class="bg-dealytics-pink/20"
                value-class="text-dealytics-pink"
            />
        </div>
        <div v-reveal class="mb-6 grid gap-4 lg:grid-cols-2">
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
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            size="sm"
                            class="w-full gap-2 border border-dealytics-purple/40 bg-dealytics-purple/15 text-xs text-dealytics-purple hover:border-dealytics-purple/60 hover:bg-dealytics-purple/25 hover:text-dealytics-purple"
                        >
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
                                    Vous vous êtes fixé une limite, ce n'est pas une bonne idée de la dépasser !
                                </AlertDescription>
                            </Alert>
                            <Input v-model="newPurchaseTitle" placeholder="Nom du jeu" class="text-sm" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="newPurchasePrice" type="number" step="0.01" min="0" placeholder="Prix payé (€)" class="text-sm" />
                                <Input v-model="newPurchaseOriginal" type="number" step="0.01" min="0" placeholder="Prix original (€)" class="text-sm" />
                            </div>
                            <Input v-model="newPurchaseStore" placeholder="Magasin (ex: Steam)" class="text-sm" />
                            <div class="space-y-1.5">
                                <label for="purchase-date" class="text-xs text-muted-foreground">Date d'achat</label>
                                <Input
                                    id="purchase-date"
                                    v-model="newPurchaseDate"
                                    type="date"
                                    class="text-sm"
                                    :max="todayDateInputValue()"
                                />
                            </div>
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
        <div v-reveal class="grid gap-4 lg:grid-cols-2">
            <div id="price-alerts" class="scroll-mt-24 border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center gap-2">
                    <Bell class="size-4 text-dealytics-cyan" />
                    <h2 class="font-heading text-lg font-semibold">Alertes de prix</h2>
                </div>
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
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Target class="size-4 text-dealytics-pink" />
                        <h2 class="font-heading text-lg font-semibold">Top Offres du Moment</h2>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-medium text-dealytics-pink transition-colors hover:text-dealytics-pink/80"
                        @click="goToTopOffers"
                    >
                        Voir tout
                        <ArrowRight class="size-3.5" />
                    </button>
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
