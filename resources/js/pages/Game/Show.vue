<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Heart,
    Bell,
    Star,
    Store,
    TrendingDown,
    DollarSign,
    Calendar,
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import DealBadge from '@/components/DealBadge.vue';
import PriceHistoryChart from '@/components/PriceHistoryChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAlerts } from '@/composables/useAlerts';

interface GameDeal {
    storeID: string;
    dealID: string;
    price: string;
    retailPrice: string;
    savings: string;
}

interface GameInfo {
    title: string;
    steamAppID: string | null;
    thumb: string;
}

interface CheapestPrice {
    price: string;
    date: number;
}

interface GameData {
    info: GameInfo;
    cheapestPriceEver: CheapestPrice;
    deals: GameDeal[];
}

interface PricePoint {
    date: number;
    price: number;
    store: string;
}

const page = usePage<{ gameId: string }>();
const gameId = page.props.gameId;

const { addAlert, getAlert, removeAlert } = useAlerts();

const game = ref<GameData | null>(null);
const loading = ref(true);
const priceHistory = ref<PricePoint[]>([]);
const alertPrice = ref('');
const alertSet = ref(false);

// Extra deal info for consistent score calculation
const dealRating = ref(0);
const metacriticScore = ref(0);
const steamRatingPercent = ref(0);

const storeNames: Record<string, string> = {
    '1': 'Steam',
    '2': 'GamersGate',
    '3': 'GreenManGaming',
    '7': 'GOG',
    '8': 'Origin',
    '11': 'Humble Bundle',
    '13': 'Uplay',
    '15': 'Fanatical',
    '21': 'WinGameStore',
    '23': 'GameBillet',
    '24': 'Voidu',
    '25': 'Epic Games',
    '27': 'Gamesplanet',
    '28': 'Gamesload',
    '29': '2Game',
    '30': 'IndieGala',
    '31': 'Blizzard',
    '33': 'DLGamer',
    '34': 'Noctre',
    '35': 'DreamGame',
};

const bestDeal = computed(() => {
    if (!game.value?.deals.length) {
return null;
}

    return game.value.deals.reduce((best, deal) =>
        parseFloat(deal.price) < parseFloat(best.price) ? deal : best,
    );
});

const currentPrice = computed(() => (bestDeal.value ? parseFloat(bestDeal.value.price) : 0));
const normalPrice = computed(() => (bestDeal.value ? parseFloat(bestDeal.value.retailPrice) : 0));
const savingsPercent = computed(() => (bestDeal.value ? Math.round(parseFloat(bestDeal.value.savings)) : 0));
const cheapestEver = computed(() => (game.value ? parseFloat(game.value.cheapestPriceEver.price) : 0));

// Same formula as GameCard.vue for consistency
const qualityPriceScore = computed(() => {
    if (!game.value || !bestDeal.value) {
        return 0;
    }

    const dealScore = Math.min(dealRating.value * 10, 100);
    const savingsScore = Math.min(savingsPercent.value * 1.2, 100);
    const qualityScore = metacriticScore.value > 0
        ? metacriticScore.value
        : steamRatingPercent.value > 0
            ? steamRatingPercent.value
            : 50;

    return Math.round(dealScore * 0.35 + savingsScore * 0.35 + qualityScore * 0.3);
});

const scoreColor = computed(() => {
    if (qualityPriceScore.value >= 80) {
        return 'text-dealytics-cyan';
    }

    if (qualityPriceScore.value >= 60) {
        return 'text-dealytics-purple';
    }

    if (qualityPriceScore.value >= 40) {
        return 'text-yellow-400';
    }

    return 'text-muted-foreground';
});

const scoreLabel = computed(() => {
    if (qualityPriceScore.value >= 80) {
        return 'Excellent';
    }

    if (qualityPriceScore.value >= 60) {
        return 'Bon deal';
    }

    if (qualityPriceScore.value >= 40) {
        return 'Moyen';
    }

    return 'Faible';
});

const scoreBorderColor = computed(() => {
    if (qualityPriceScore.value >= 80) {
        return 'border-dealytics-cyan/50 bg-dealytics-cyan/10';
    }

    if (qualityPriceScore.value >= 60) {
        return 'border-dealytics-purple/50 bg-dealytics-purple/10';
    }

    if (qualityPriceScore.value >= 40) {
        return 'border-yellow-400/50 bg-yellow-400/10';
    }

    return 'border-border bg-secondary/50';
});

const scoreDetails = computed(() => {
    const dealRatingRaw = dealRating.value;
    const savings = savingsPercent.value;

    return {
        dealVal: Math.round(Math.min(dealRatingRaw * 10, 100)),
        dealLabel: `${dealRatingRaw.toFixed(1)}/10`,
        savingsVal: Math.round(savings),
        savingsLabel: `${Math.round(savings)}%`,
        qualityVal: Math.round(
            metacriticScore.value > 0
                ? metacriticScore.value
                : steamRatingPercent.value > 0
                    ? steamRatingPercent.value
                    : 50,
        ),
        qualityLabel: metacriticScore.value > 0
            ? `${Math.round(metacriticScore.value)}/100`
            : steamRatingPercent.value > 0
                ? `${Math.round(steamRatingPercent.value)}%`
                : 'N/A',
        qualitySource: metacriticScore.value > 0 ? 'Metacritic' : steamRatingPercent.value > 0 ? 'Steam' : 'Qualité',
    };
});

const cheapestDate = computed(() =>
    game.value
        ? new Date(game.value.cheapestPriceEver.date * 1000).toLocaleDateString('fr-FR', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '',
);

const steamHeaderImage = computed(() => {
    if (game.value?.info.steamAppID) {
        return `https://cdn.akamai.steamstatic.com/steam/apps/${game.value.info.steamAppID}/header.jpg`;
    }

    return game.value?.info.thumb || '';
});

// Favorite logic
const isFavorite = computed(() => {
    try {
        const favs = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');

        return favs.some((f: { gameID: string }) => f.gameID === gameId);
    } catch {
        return false;
    }
});

function toggleFavorite() {
    try {
        const favs = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');
        const idx = favs.findIndex((f: { gameID: string }) => f.gameID === gameId);

        if (idx >= 0) {
            favs.splice(idx, 1);
        } else {
            favs.push({
                gameID: gameId,
                title: game.value?.info.title || '',
                thumb: game.value?.info.thumb || '',
                addedAt: new Date().toISOString(),
            });
        }

        localStorage.setItem('dealytics_favorites', JSON.stringify(favs));
    } catch {
        // silently fail
    }
}

// Alert logic
function setAlertPrice() {
    if (!alertPrice.value) {
return;
}

    addAlert(gameId, game.value?.info.title || '', parseFloat(alertPrice.value));
    alertSet.value = true;
}

function clearAlert() {
    removeAlert(gameId);
    alertSet.value = false;
    alertPrice.value = '';
}

onMounted(async () => {
    try {
        // Fetch game data
        const response = await fetch(`https://www.cheapshark.com/api/1.0/games?id=${gameId}`);
        const data: GameData = await response.json();
        game.value = data;

        // Fetch deal info from deals LIST endpoint (has dealRating + metacritic)
        // The single deal endpoint /deals?id=X does NOT return dealRating
        if (data.info?.title) {
            try {
                const params = new URLSearchParams();
                params.set('sortBy', 'Deal Rating');
                params.set('title', data.info.title);
                params.set('exact', '1');
                params.set('pageSize', '1');

                const dealResponse = await fetch(
                    `https://www.cheapshark.com/api/1.0/deals?${params.toString()}`,
                );
                const dealList = await dealResponse.json();

                if (Array.isArray(dealList) && dealList.length > 0) {
                    dealRating.value = parseFloat(dealList[0].dealRating || '0');
                    metacriticScore.value = parseFloat(dealList[0].metacriticScore || '0');
                    steamRatingPercent.value = parseFloat(dealList[0].steamRatingPercent || '0');
                }
            } catch {
                // Score will use fallback values
            }
        }

        // Build price history from deals (CheapShark doesn't have a dedicated history endpoint per game,
        // but we can use the cheapest price ever + current deals to build a simplified view)
        const points: PricePoint[] = [];

        // Add cheapest ever as a historical point
        if (data.cheapestPriceEver) {
            points.push({
                date: data.cheapestPriceEver.date,
                price: parseFloat(data.cheapestPriceEver.price),
                store: 'Meilleur prix',
            });
        }

        // Add current deals as recent points
        for (const deal of data.deals) {
            points.push({
                date: Math.floor(Date.now() / 1000),
                price: parseFloat(deal.price),
                store: storeNames[deal.storeID] || 'Store',
            });
        }

        // Generate some interpolated historical points for a better chart
        if (data.cheapestPriceEver && data.deals.length > 0) {
            const cheapDate = data.cheapestPriceEver.date;
            const now = Math.floor(Date.now() / 1000);
            const retailPrice = parseFloat(data.deals[0].retailPrice);
            const cheapPrice = parseFloat(data.cheapestPriceEver.price);
            const steps = 6;

            for (let i = 1; i < steps; i++) {
                const t = cheapDate + ((now - cheapDate) * i) / steps;
                // Simulate price fluctuation between cheap and retail
                const factor = Math.sin((i / steps) * Math.PI) * 0.4 + 0.5;
                const price = cheapPrice + (retailPrice - cheapPrice) * factor;
                points.push({
                    date: Math.floor(t),
                    price: Math.round(price * 100) / 100,
                    store: 'Estimation',
                });
            }
        }

        priceHistory.value = points.sort((a, b) => a.date - b.date);

        // Check existing alert
        const existingAlert = getAlert(gameId);

        if (existingAlert) {
            alertPrice.value = existingAlert.targetPrice.toString();
            alertSet.value = true;
        }
    } catch {
        // handle error
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="game?.info.title || 'Chargement...'" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Back button -->
        <Link
            href="/"
            class="mb-6 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Retour à l'accueil
        </Link>

        <!-- Loading state -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <div class="size-8 animate-spin rounded-full border-2 border-dealytics-purple border-t-transparent" />
            <p class="mt-4 text-sm text-muted-foreground">Chargement du jeu...</p>
        </div>

        <template v-else-if="game">
            <!-- Hero image + title -->
            <div class="relative mb-8 overflow-hidden rounded-2xl border-gradient-strong">
                <div class="relative aspect-[21/9] overflow-hidden">
                    <img
                        :src="steamHeaderImage"
                        :alt="game.info.title"
                        class="size-full object-cover"
                        @error="($event.target as HTMLImageElement).src = game!.info.thumb"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

                    <!-- Content overlay -->
                    <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8">
                        <h1 class="font-heading text-3xl font-bold text-white md:text-4xl">
                            {{ game.info.title }}
                        </h1>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <!-- Deal Badge -->
                            <DealBadge
                                :current-price="currentPrice"
                                :lowest-price="cheapestEver"
                                :normal-price="normalPrice"
                                :savings="savingsPercent"
                            />

                            <!-- Price -->
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold text-dealytics-cyan">
                                    {{ currentPrice === 0 ? 'Gratuit' : `$${currentPrice.toFixed(2)}` }}
                                </span>
                                <span v-if="savingsPercent > 0" class="text-lg text-white/50 line-through">
                                    ${{ normalPrice.toFixed(2) }}
                                </span>
                                <span
                                    v-if="savingsPercent > 0"
                                    class="rounded-md bg-dealytics-purple px-2 py-0.5 text-sm font-bold text-white"
                                >
                                    -{{ savingsPercent }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left column: chart + deals -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Price History Chart -->
                    <div class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <TrendingDown class="size-4 text-dealytics-purple" />
                            <h2 class="font-heading text-lg font-semibold">Historique des Prix</h2>
                        </div>

                        <PriceHistoryChart
                            v-if="priceHistory.length > 1"
                            :price-history="priceHistory"
                            :current-price="currentPrice"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas assez de données pour afficher l'historique.
                        </p>

                        <!-- Price stats -->
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <DollarSign class="mx-auto mb-1 size-4 text-dealytics-cyan" />
                                <div class="text-sm font-semibold text-dealytics-cyan">
                                    ${{ cheapestEver.toFixed(2) }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Prix le plus bas</div>
                            </div>
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <Calendar class="mx-auto mb-1 size-4 text-dealytics-purple" />
                                <div class="text-xs font-semibold text-dealytics-purple">
                                    {{ cheapestDate }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Date du minimum</div>
                            </div>
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <DollarSign class="mx-auto mb-1 size-4 text-foreground" />
                                <div class="text-sm font-semibold text-foreground">
                                    ${{ normalPrice.toFixed(2) }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Prix de base</div>
                            </div>
                        </div>
                    </div>

                    <!-- All Deals -->
                    <div class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <Store class="size-4 text-dealytics-cyan" />
                            <h2 class="font-heading text-lg font-semibold">
                                Comparer les prix ({{ game.deals.length }} offres)
                            </h2>
                        </div>

                        <div class="space-y-2">
                            <a
                                v-for="deal in game.deals"
                                :key="deal.dealID"
                                :href="`https://www.cheapshark.com/redirect?dealID=${deal.dealID}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-between rounded-lg bg-secondary/50 p-3 transition-colors hover:bg-secondary"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 items-center justify-center rounded-lg bg-background">
                                        <Store class="size-4 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">
                                            {{ storeNames[deal.storeID] || `Store #${deal.storeID}` }}
                                        </div>
                                        <div v-if="parseFloat(deal.savings) > 0" class="text-[10px] text-dealytics-pink">
                                            -{{ Math.round(parseFloat(deal.savings)) }}% de réduction
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-dealytics-cyan">
                                            ${{ parseFloat(deal.price).toFixed(2) }}
                                        </div>
                                        <div
                                            v-if="parseFloat(deal.savings) > 0"
                                            class="text-[10px] text-muted-foreground line-through"
                                        >
                                            ${{ parseFloat(deal.retailPrice).toFixed(2) }}
                                        </div>
                                    </div>
                                    <ExternalLink class="size-3.5 text-muted-foreground" />
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right column: actions -->
                <div class="space-y-6">
                    <!-- Actions -->
                    <div class="border-gradient rounded-xl p-6">
                        <h3 class="mb-4 font-heading text-base font-semibold">Actions</h3>

                        <div class="space-y-3">
                            <!-- Favorite button -->
                            <Button
                                class="w-full gap-2"
                                :variant="isFavorite ? 'default' : 'outline'"
                                @click="toggleFavorite"
                            >
                                <Heart
                                    class="size-4"
                                    :class="isFavorite ? 'fill-white' : ''"
                                />
                                {{ isFavorite ? 'Dans vos favoris' : 'Ajouter aux favoris' }}
                            </Button>

                            <!-- Buy button -->
                            <Button
                                v-if="bestDeal"
                                class="w-full gap-2 bg-dealytics-cyan text-dealytics-dark hover:bg-dealytics-cyan/90"
                                as-child
                            >
                                <a
                                    :href="`https://www.cheapshark.com/redirect?dealID=${bestDeal.dealID}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink class="size-4" />
                                    Acheter sur {{ storeNames[bestDeal.storeID] || 'Store' }}
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Price Alert -->
                    <div class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <Bell class="size-4 text-dealytics-cyan" />
                            <h3 class="font-heading text-base font-semibold">Alerte de prix</h3>
                        </div>

                        <p class="mb-3 text-xs text-muted-foreground">
                            Définissez un prix cible et soyez notifié quand il est atteint.
                        </p>

                        <div v-if="alertSet" class="rounded-lg bg-dealytics-cyan/10 p-3 text-center">
                            <Bell class="mx-auto mb-1 size-5 text-dealytics-cyan" />
                            <p class="text-sm font-medium text-dealytics-cyan">
                                Alerte active : ${{ alertPrice }}
                            </p>
                            <button
                                class="mt-1 text-[10px] text-muted-foreground hover:text-foreground"
                                @click="clearAlert"
                            >
                                Supprimer l'alerte
                            </button>
                        </div>

                        <div v-else class="flex gap-2">
                            <Input
                                v-model="alertPrice"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Prix cible ($)"
                                class="h-9 text-sm"
                            />
                            <Button
                                size="sm"
                                class="shrink-0 bg-dealytics-purple hover:bg-dealytics-deep-purple"
                                :disabled="!alertPrice"
                                @click="setAlertPrice"
                            >
                                <Bell class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Game Info -->
                    <div class="border-gradient rounded-xl p-6">
                        <h3 class="mb-4 font-heading text-base font-semibold">Informations</h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Steam App ID</dt>
                                <dd class="font-medium">{{ game.info.steamAppID || 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Offres disponibles</dt>
                                <dd class="font-medium">{{ game.deals.length }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Meilleur prix actuel</dt>
                                <dd class="font-medium text-dealytics-cyan">${{ currentPrice.toFixed(2) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Plus bas historique</dt>
                                <dd class="font-medium text-dealytics-pink">${{ cheapestEver.toFixed(2) }}</dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-border/50 pt-3">
                                <dt class="text-muted-foreground">Score qualité/prix</dt>
                                <dd class="flex items-center gap-2">
                                    <TooltipProvider :delay-duration="200">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <div
                                                    class="flex size-9 cursor-help items-center justify-center rounded-full border text-sm font-bold transition-transform hover:scale-110"
                                                    :class="[scoreColor, scoreBorderColor]"
                                                >
                                                    {{ qualityPriceScore }}
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                side="left"
                                                :side-offset="8"
                                                class="w-56 border-border/50 bg-card p-3 text-card-foreground shadow-xl"
                                            >
                                                <div class="space-y-2.5">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-semibold" :class="scoreColor">{{ scoreLabel }}</span>
                                                        <span class="text-xs font-bold" :class="scoreColor">{{ qualityPriceScore }}/100</span>
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <div class="flex items-center justify-between text-[11px]">
                                                            <span class="text-muted-foreground">Note du deal</span>
                                                            <span class="font-medium">{{ scoreDetails.dealLabel }}</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-purple transition-all" :style="{ width: `${scoreDetails.dealVal}%` }" />
                                                        </div>
                                                        <div class="flex items-center justify-between text-[11px]">
                                                            <span class="text-muted-foreground">Réduction</span>
                                                            <span class="font-medium">{{ scoreDetails.savingsLabel }}</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-cyan transition-all" :style="{ width: `${scoreDetails.savingsVal}%` }" />
                                                        </div>
                                                        <div class="flex items-center justify-between text-[11px]">
                                                            <span class="text-muted-foreground">{{ scoreDetails.qualitySource }}</span>
                                                            <span class="font-medium">{{ scoreDetails.qualityLabel }}</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-pink transition-all" :style="{ width: `${scoreDetails.qualityVal}%` }" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <span class="text-xs" :class="scoreColor">/100</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </template>

        <!-- Error state -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <Star class="mb-4 size-16 text-muted-foreground/20" />
            <h3 class="font-heading text-lg font-semibold">Jeu introuvable</h3>
            <p class="mt-1 text-sm text-muted-foreground">Ce jeu n'existe pas ou n'est plus disponible.</p>
            <Link href="/" class="mt-4 text-sm text-dealytics-purple hover:underline">
                Retour à l'accueil
            </Link>
        </div>
    </div>
</template>
