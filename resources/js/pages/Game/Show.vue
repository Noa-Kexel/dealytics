<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Heart,
    Bell,
    Star,
    Store,
    Flame,
    Calendar,
    Gamepad2,
    Monitor,
    Code,
    Tag,
    Clock,
    ChevronLeft,
    ChevronRight,
    BarChart3,
    TrendingDown,
    CalendarClock,
    Image as ImageIcon,
} from 'lucide-vue-next';
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import DealBadge from '@/components/DealBadge.vue';
import GameImage from '@/components/GameImage.vue';
import PriceHistoryChart from '@/components/PriceHistoryChart.vue';
import StorePriceChart from '@/components/StorePriceChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAlerts } from '@/composables/useAlerts';
import { useFavorites } from '@/composables/useFavorites';
import {
    getQualityPriceScore,
    getQualityValue,
    getPriceValue,
    getScoreBorderColor,
    getScoreColor,
    getScoreLabel,
    hasQualityData as checkHasQualityData,
} from '@/lib/qualityPriceScore';

interface NexardaOffer {
    url: string | null;
    store: string;
    storeImage: string | null;
    storeType: string;
    official: boolean;
    edition: string | null;
    editionFull: string | null;
    platform: string | null;
    region: string | null;
    price: number;
    discount: number;
    coupon: { code: string; discount: number; priceWithout: number } | null;
}

interface NexardaData {
    game: { id: number; name: string; cover: string | null };
    currency: string;
    currencySymbol: string;
    lowest: number | null;
    highest: number | null;
    maxDiscount: number;
    storeCount: number;
    offerCount: number;
    editions: string[];
    offers: NexardaOffer[];
}

interface RawgData {
    source?: 'rawg' | 'steam';
    id: number;
    name: string;
    description: string;
    released: string | null;
    background_image: string | null;
    rating: number;
    ratings_count: number;
    metacritic: number | null;
    playtime: number;
    genres: string[];
    platforms: string[];
    developers: string[];
    publishers: string[];
    tags: string[];
    screenshots: string[];
    website: string | null;
}

const page = usePage<{ gameId: string }>();
const gameId = page.props.gameId;

const { addAlert, getAlert, removeAlert } = useAlerts();

interface PricePoint {
    date: number;
    price: number;
    store: string;
}

const nexarda = ref<NexardaData | null>(null);
const loading = ref(true);
const alertPrice = ref('');
const alertSet = ref(false);

// Real price history — from ITAD (full series) or our daily snapshots.
const priceHistory = ref<PricePoint[]>([]);
const historySource = ref<string | null>(null);
const hasHistory = computed(() => priceHistory.value.length >= 2);

// Lowest price observed over our tracking window (snapshots + current price).
const lowestPoint = computed(() => {
    if (!priceHistory.value.length) {
        return null;
    }

    return priceHistory.value.reduce((min, p) => (p.price < min.price ? p : min));
});
const lowestEver = computed(() => {
    const fromHistory = lowestPoint.value?.price ?? Infinity;

    return Math.min(fromHistory, currentPrice.value || Infinity);
});
const lowestEverDate = computed(() => {
    if (!lowestPoint.value) {
        return null;
    }

    return new Date(lowestPoint.value.date * 1000).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
const priceWindow = computed(() => {
    const prices = priceHistory.value.map((p) => p.price);

    return prices.length
        ? { min: Math.min(...prices), max: Math.max(...prices) }
        : { min: 0, max: 0 };
});
// Only celebrate "at the lowest" when the price has actually moved down to it,
// not when it has simply been flat the whole tracking window.
const isAtLowest = computed(
    () =>
        hasHistory.value &&
        priceWindow.value.max > priceWindow.value.min &&
        currentPrice.value <= lowestEver.value * 1.02,
);

// RAWG enrichment
const rawg = ref<RawgData | null>(null);
const rawgLoading = ref(false);
const screenshotIndex = ref(0);

const title = computed(() => nexarda.value?.game.name || rawg.value?.name || '');
const currencySymbol = computed(() => nexarda.value?.currencySymbol || '€');

const heroImage = computed(
    () => rawg.value?.background_image || nexarda.value?.game.cover || '',
);

const coverImage = computed(() => nexarda.value?.game.cover || rawg.value?.background_image || '');

// Cheapest available offer drives the headline price.
const bestOffer = computed(() => {
    if (!nexarda.value?.offers.length) {
        return null;
    }

    return nexarda.value.offers.reduce((best, offer) =>
        offer.price < best.price ? offer : best,
    );
});

const currentPrice = computed(() => nexarda.value?.lowest ?? bestOffer.value?.price ?? 0);
const savingsPercent = computed(() => Math.round(bestOffer.value?.discount ?? nexarda.value?.maxDiscount ?? 0));
const normalPrice = computed(() => {
    if (savingsPercent.value > 0 && savingsPercent.value < 100) {
        return currentPrice.value / (1 - savingsPercent.value / 100);
    }

    return nexarda.value?.highest ?? currentPrice.value;
});

const PLATFORM_LABELS: Record<string, string> = {
    WINDOWS: 'PC',
    MAC: 'Mac',
    LINUX: 'Linux',
    'XBOX-XS': 'Xbox Series',
    'XBOX-ONE': 'Xbox One',
    XBOX: 'Xbox',
    PS5: 'PlayStation 5',
    PS4: 'PlayStation 4',
    SWITCH: 'Nintendo Switch',
    'SWITCH-2': 'Nintendo Switch 2',
};

function extractOfferPlatform(offer: NexardaOffer): string | null {
    if (offer.platform) {
        return offer.platform;
    }

    const match = offer.editionFull?.match(/FOR:([A-Z0-9-]+)/i);

    return match ? match[1].toUpperCase() : null;
}

function platformLabel(slug: string): string {
    return PLATFORM_LABELS[slug] ?? slug.replace(/-/g, ' ');
}

function cheapestOffer(offers: NexardaOffer[], official?: boolean): NexardaOffer | null {
    const pool = official === undefined
        ? offers
        : offers.filter((o) => o.official === official);

    if (!pool.length) {
        return null;
    }

    return pool.reduce((best, offer) => (offer.price < best.price ? offer : best));
}

const selectedOfferPlatform = ref('all');

const offerPlatforms = computed(() => {
    const counts = new Map<string, number>();

    for (const offer of nexarda.value?.offers ?? []) {
        const platform = extractOfferPlatform(offer);

        if (!platform) {
            continue;
        }

        counts.set(platform, (counts.get(platform) ?? 0) + 1);
    }

    return Array.from(counts.entries()).sort((a, b) => b[1] - a[1]);
});

const filteredOffers = computed(() => {
    const offers = nexarda.value?.offers ?? [];

    if (selectedOfferPlatform.value === 'all') {
        return offers;
    }

    return offers.filter((offer) => extractOfferPlatform(offer) === selectedOfferPlatform.value);
});

const filteredBestOfficialPrice = computed(() => cheapestOffer(filteredOffers.value, true));
const filteredBestKeyshopPrice = computed(() => cheapestOffer(filteredOffers.value, false));

watch(
    () => nexarda.value?.offers,
    () => {
        selectedOfferPlatform.value = 'all';
    },
);

// Quality/price score (/100) — combines RAWG quality and current discount.
const hasQualityData = computed(() =>
    checkHasQualityData(rawg.value?.metacritic, rawg.value?.rating),
);
const qualityValue = computed(() =>
    getQualityValue(rawg.value?.metacritic, rawg.value?.rating),
);
// Original (no-promo) price = the highest current offer across stores, i.e.
// a store still selling at full price. Falls back to the discount-derived
// normal price. This drives the price-value component of the score.
const originalPrice = computed(() =>
    Math.max(nexarda.value?.highest ?? 0, normalPrice.value),
);
const priceValue = computed(() => getPriceValue(currentPrice.value, originalPrice.value));
const qualityPriceScore = computed(() =>
    getQualityPriceScore(
        priceValue.value,
        rawg.value?.metacritic,
        rawg.value?.rating,
    ),
);
const scoreColor = computed(() => getScoreColor(qualityPriceScore.value));
const scoreBorderColor = computed(() => getScoreBorderColor(qualityPriceScore.value));
const scoreLabel = computed(() => getScoreLabel(qualityPriceScore.value));

// Favorite logic (via composable — persists to DB when authenticated)
const { favoriteIds, toggleFavorite: toggleFav, loadFavorites } = useFavorites();
const heartAnimating = ref(false);

const isFavorite = computed(() => favoriteIds.value.has(gameId));

async function toggleFavorite() {
    await toggleFav(gameId, title.value, coverImage.value);

    heartAnimating.value = false;
    await nextTick();
    heartAnimating.value = true;
    setTimeout(() => {
        heartAnimating.value = false;
    }, 400);
}

// Alert logic
const alertError = ref('');

async function setAlertPrice() {
    const value = parseFloat(alertPrice.value);

    if (!alertPrice.value || Number.isNaN(value)) {
        alertError.value = 'Entre un prix valide.';

        return;
    }

    if (value <= 0) {
        alertError.value = 'Le prix doit être supérieur à 0.';

        return;
    }

    if (value > 1000) {
        alertError.value = 'Prix trop élevé (max 1000€).';

        return;
    }

    alertError.value = '';
    await addAlert(gameId, title.value, Math.round(value * 100) / 100);
    alertSet.value = true;
}

function clearAlert() {
    removeAlert(gameId);
    alertSet.value = false;
    alertPrice.value = '';
    alertError.value = '';
}

// RAWG screenshot navigation
function prevScreenshot() {
    if (rawg.value && rawg.value.screenshots.length > 0) {
        screenshotIndex.value = (screenshotIndex.value - 1 + rawg.value.screenshots.length) % rawg.value.screenshots.length;
    }
}

function nextScreenshot() {
    if (rawg.value && rawg.value.screenshots.length > 0) {
        screenshotIndex.value = (screenshotIndex.value + 1) % rawg.value.screenshots.length;
    }
}

const ratingLabel = computed(() =>
    rawg.value?.source === 'steam' ? 'Note Steam' : 'Note joueurs',
);

const rawgReleaseDate = computed(() => {
    if (!rawg.value?.released) {
        return null;
    }

    const parsed = new Date(rawg.value.released);

    if (Number.isNaN(parsed.getTime())) {
        return rawg.value.released;
    }

    return parsed.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const shortDescription = ref(true);
const descriptionIsLong = computed(() => (rawg.value?.description?.length ?? 0) > 400);

onMounted(async () => {
    loadFavorites();

    try {
        // Primary data: Nexarda prices by game id
        const response = await fetch(`/api/nexarda/game/${gameId}`);

        if (response.ok) {
            nexarda.value = await response.json();
        }

        // Real price history — ITAD (full series) with snapshot fallback.
        try {
            const titleParam = encodeURIComponent(nexarda.value?.game.name ?? '');
            const historyResponse = await fetch(
                `/api/games/${gameId}/history?title=${titleParam}`,
            );

            if (historyResponse.ok) {
                const { history, source } = await historyResponse.json();
                historySource.value = source ?? null;
                priceHistory.value = (history ?? []).map(
                    (p: { date: number; price: number }) => ({
                        date: p.date,
                        price: p.price,
                        store: 'Meilleur prix',
                    }),
                );
            }
        } catch {
            // history is optional
        }

        // Check existing alert
        const existingAlert = getAlert(gameId);

        if (existingAlert) {
            alertPrice.value = (existingAlert.target_price ?? existingAlert.targetPrice ?? '').toString();
            alertSet.value = true;
        }

        // RAWG enrichment by title
        if (nexarda.value?.game.name) {
            rawgLoading.value = true;

            try {
                const rawgResponse = await fetch(`/api/rawg/${encodeURIComponent(nexarda.value.game.name)}`);

                if (rawgResponse.ok) {
                    rawg.value = await rawgResponse.json();
                }
            } catch {
                // enrichment is optional
            }

            rawgLoading.value = false;
        }
    } catch {
        // handle error
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="title || 'Chargement...'" />

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

        <template v-else-if="nexarda">
            <!-- Hero image + title -->
            <div class="relative mb-8 overflow-hidden rounded-2xl border-gradient-strong">
                <div class="relative aspect-[21/9] overflow-hidden">
                    <GameImage
                        :src="heroImage"
                        :alt="title"
                        class="size-full object-cover"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

                    <!-- Content overlay -->
                    <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8">
                        <h1 class="font-heading text-3xl font-bold text-white md:text-4xl">
                            {{ title }}
                        </h1>

                        <!-- RAWG metadata tags -->
                        <div v-if="rawg" class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                v-for="genre in rawg.genres"
                                :key="genre"
                                class="rounded-full bg-dealytics-purple/30 px-2.5 py-0.5 text-[10px] font-medium text-dealytics-purple backdrop-blur-sm"
                            >
                                {{ genre }}
                            </span>
                            <span v-if="rawgReleaseDate" class="flex items-center gap-1 text-[10px] text-white/50">
                                <Calendar class="size-2.5" />
                                {{ rawgReleaseDate }}
                            </span>
                            <span v-if="rawg.rating > 0" class="flex items-center gap-1 rounded-full bg-dealytics-cyan/20 px-2 py-0.5 text-[10px] font-medium text-dealytics-cyan backdrop-blur-sm">
                                <Star class="size-2.5 fill-dealytics-cyan" />
                                {{ rawg.rating.toFixed(1) }}/5
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <!-- Deal Badge -->
                            <DealBadge
                                :current-price="currentPrice"
                                :lowest-price="lowestEver"
                                :normal-price="normalPrice"
                                :savings="savingsPercent"
                            />

                            <!-- Price -->
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold text-dealytics-cyan">
                                    {{ currentPrice === 0 ? 'Indisponible' : `${currentPrice.toFixed(2)}${currencySymbol}` }}
                                </span>
                                <span v-if="savingsPercent > 0" class="text-lg text-white/50 line-through">
                                    {{ normalPrice.toFixed(2) }}{{ currencySymbol }}
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
                <!-- Left column: about + screenshots + offers -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- RAWG Description -->
                    <div v-if="rawgLoading" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 h-5 w-40 animate-pulse rounded bg-secondary" />
                        <div class="space-y-2">
                            <div class="h-3 w-full animate-pulse rounded bg-secondary" />
                            <div class="h-3 w-5/6 animate-pulse rounded bg-secondary" />
                            <div class="h-3 w-4/6 animate-pulse rounded bg-secondary" />
                        </div>
                    </div>
                    <div v-else-if="rawg?.description" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 flex items-center gap-2">
                            <Gamepad2 class="size-4 text-dealytics-purple" />
                            <h2 class="font-heading text-lg font-semibold">À propos</h2>
                        </div>
                        <div class="relative">
                            <div
                                class="game-description text-sm leading-relaxed text-muted-foreground"
                                :class="shortDescription && descriptionIsLong ? 'max-h-48 overflow-hidden' : ''"
                                v-html="rawg.description"
                            />
                            <div
                                v-if="shortDescription && descriptionIsLong"
                                class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-card to-transparent"
                            />
                        </div>
                        <button
                            v-if="descriptionIsLong"
                            class="mt-3 text-xs font-medium text-dealytics-purple hover:underline"
                            @click="shortDescription = !shortDescription"
                        >
                            {{ shortDescription ? 'Lire la suite ↓' : 'Réduire ↑' }}
                        </button>

                        <!-- Platforms & Developers inline -->
                        <div v-if="rawg.platforms.length || rawg.developers.length" class="mt-4 flex flex-wrap gap-4 border-t border-border/50 pt-4">
                            <div v-if="rawg.platforms.length" class="flex items-start gap-2">
                                <Monitor class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="p in rawg.platforms"
                                        :key="p"
                                        class="rounded bg-secondary/80 px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ p }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="rawg.developers.length" class="flex items-start gap-2">
                                <Code class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <span class="text-xs text-muted-foreground">{{ rawg.developers.join(', ') }}</span>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div v-if="rawg.tags.length" class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in rawg.tags"
                                :key="tag"
                                class="flex items-center gap-1 rounded-full bg-secondary/60 px-2 py-0.5 text-[10px] text-muted-foreground"
                            >
                                <Tag class="size-2" />
                                {{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- RAWG Screenshots Gallery -->
                    <div v-if="rawgLoading" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 h-5 w-32 animate-pulse rounded bg-secondary" />
                        <div class="aspect-video w-full animate-pulse rounded-lg bg-secondary" />
                    </div>
                    <div v-else-if="rawg?.screenshots?.length" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ImageIcon class="size-4 text-dealytics-cyan" />
                                <h2 class="font-heading text-lg font-semibold">Screenshots</h2>
                                <span class="text-xs text-muted-foreground">({{ screenshotIndex + 1 }}/{{ rawg.screenshots.length }})</span>
                            </div>
                            <div class="flex gap-1.5">
                                <button
                                    class="flex size-7 items-center justify-center rounded-lg bg-secondary/80 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    @click="prevScreenshot"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <button
                                    class="flex size-7 items-center justify-center rounded-lg bg-secondary/80 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    @click="nextScreenshot"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>
                        <div class="relative overflow-hidden rounded-lg">
                            <Transition name="screenshot-fade" mode="out-in">
                                <img
                                    :key="screenshotIndex"
                                    :src="rawg.screenshots[screenshotIndex]"
                                    :alt="`Screenshot ${screenshotIndex + 1}`"
                                    class="aspect-video w-full object-cover"
                                />
                            </Transition>
                        </div>
                        <!-- Thumbnail strip -->
                        <div v-if="rawg.screenshots.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                            <button
                                v-for="(url, idx) in rawg.screenshots"
                                :key="idx"
                                class="shrink-0 overflow-hidden rounded-md border-2 transition-all"
                                :class="idx === screenshotIndex ? 'border-dealytics-cyan opacity-100' : 'border-transparent opacity-50 hover:opacity-75'"
                                @click="screenshotIndex = idx"
                            >
                                <img :src="url" :alt="`Thumb ${idx + 1}`" class="h-12 w-20 object-cover" />
                            </button>
                        </div>
                    </div>

                    <!-- Price history (ITAD full series, or daily snapshots) -->
                    <div class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <TrendingDown class="size-4 text-dealytics-cyan" />
                                <h2 class="font-heading text-lg font-semibold">Historique des prix</h2>
                            </div>
                            <div v-if="hasHistory && historySource === 'itad'" class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <span>via</span>
                                <span class="font-medium text-dealytics-cyan">IsThereAnyDeal</span>
                            </div>
                        </div>

                        <PriceHistoryChart
                            v-if="hasHistory"
                            :price-history="priceHistory"
                            :current-price="currentPrice"
                            :currency-symbol="currencySymbol"
                        />
                        <div v-else class="flex flex-col items-center justify-center py-10 text-center">
                            <CalendarClock class="mb-3 size-10 text-muted-foreground/30" />
                            <p class="text-sm text-muted-foreground">
                                L'historique se construit jour après jour.
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground/70">
                                Revenez bientôt pour suivre l'évolution du prix de ce jeu.
                            </p>
                        </div>

                        <!-- Real history stats -->
                        <div v-if="priceHistory.length > 0" class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <Tag class="mx-auto mb-1 size-4 text-dealytics-cyan" />
                                <div class="text-sm font-semibold text-dealytics-cyan">
                                    {{ lowestEver.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Prix le plus bas</div>
                            </div>
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <Calendar class="mx-auto mb-1 size-4 text-dealytics-purple" />
                                <div class="text-xs font-semibold text-dealytics-purple">
                                    {{ lowestEverDate ?? '—' }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Date du minimum</div>
                            </div>
                            <div class="rounded-lg bg-secondary/50 p-3 text-center">
                                <TrendingDown class="mx-auto mb-1 size-4 text-foreground" />
                                <div class="text-sm font-semibold text-foreground">
                                    {{ currentPrice.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">Prix actuel</div>
                            </div>
                        </div>

                        <!-- At-lowest highlight -->
                        <p
                            v-if="isAtLowest"
                            class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-dealytics-pink"
                        >
                            <Flame class="size-3.5" />
                            Le prix actuel est au plus bas observé depuis le début du suivi.
                        </p>
                    </div>

                    <!-- Price comparison chart (real per-store prices) -->
                    <div v-if="filteredOffers.length > 1" class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <BarChart3 class="size-4 text-dealytics-purple" />
                            <h2 class="font-heading text-lg font-semibold">Comparaison des prix par magasin</h2>
                        </div>

                        <StorePriceChart
                            :offers="filteredOffers"
                            :currency-symbol="currencySymbol"
                        />

                        <div class="mt-4 flex items-center justify-center gap-4 text-[10px] text-muted-foreground">
                            <span class="flex items-center gap-1.5">
                                <span class="size-2.5 rounded-sm bg-dealytics-cyan/60" />
                                Store officiel
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="size-2.5 rounded-sm bg-dealytics-purple/60" />
                                Keyshop / Marketplace
                            </span>
                        </div>
                    </div>

                    <!-- NEXARDA — All stores prices (official + keyshops) -->
                    <div v-if="nexarda.offers.length" class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Store class="size-4 text-dealytics-pink" />
                                <h2 class="font-heading text-lg font-semibold">
                                    Comparer les prix ({{ nexarda.offerCount }} offres — {{ nexarda.storeCount }} stores)
                                </h2>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <span>via</span>
                                <span class="font-medium text-dealytics-pink">NEXARDA</span>
                            </div>
                        </div>

                        <!-- Platform filter -->
                        <div v-if="offerPlatforms.length > 1" class="mb-4">
                            <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                                <Monitor class="size-3.5" />
                                Filtrer par plateforme
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="selectedOfferPlatform === 'all'
                                        ? 'border-dealytics-purple bg-dealytics-purple/20 text-dealytics-purple'
                                        : 'border-border/50 bg-secondary/30 text-muted-foreground hover:border-dealytics-purple/40 hover:text-foreground'"
                                    @click="selectedOfferPlatform = 'all'"
                                >
                                    Toutes ({{ nexarda.offers.length }})
                                </button>
                                <button
                                    v-for="[slug, count] in offerPlatforms"
                                    :key="slug"
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="selectedOfferPlatform === slug
                                        ? 'border-dealytics-purple bg-dealytics-purple/20 text-dealytics-purple'
                                        : 'border-border/50 bg-secondary/30 text-muted-foreground hover:border-dealytics-purple/40 hover:text-foreground'"
                                    @click="selectedOfferPlatform = slug"
                                >
                                    {{ platformLabel(slug) }} ({{ count }})
                                </button>
                            </div>
                        </div>

                        <!-- Best official vs best keyshop summary -->
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-dealytics-cyan/20 bg-dealytics-cyan/5 p-3 text-center">
                                <div class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">Store officiel</div>
                                <div v-if="filteredBestOfficialPrice" class="mt-1 text-xl font-bold text-dealytics-cyan">
                                    {{ filteredBestOfficialPrice.price.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div v-else class="mt-1 text-sm text-muted-foreground">Indisponible</div>
                                <div v-if="filteredBestOfficialPrice" class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ filteredBestOfficialPrice.store }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-yellow-400/20 bg-yellow-400/5 p-3 text-center">
                                <div class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">Keyshop / Marketplace</div>
                                <div v-if="filteredBestKeyshopPrice" class="mt-1 text-xl font-bold text-yellow-400">
                                    {{ filteredBestKeyshopPrice.price.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div v-else class="mt-1 text-sm text-muted-foreground">Indisponible</div>
                                <div v-if="filteredBestKeyshopPrice" class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ filteredBestKeyshopPrice.store }}
                                </div>
                            </div>
                        </div>

                        <!-- Full offer list -->
                        <div v-if="filteredOffers.length" class="space-y-2">
                            <a
                                v-for="(offer, idx) in filteredOffers"
                                :key="idx"
                                :href="offer.url || '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-between rounded-lg bg-secondary/50 p-3 transition-colors hover:bg-secondary"
                            >
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="offer.storeImage"
                                        :src="offer.storeImage"
                                        :alt="offer.store"
                                        class="size-8 rounded-lg object-contain"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                    />
                                    <div v-else class="flex size-8 items-center justify-center rounded-lg bg-background">
                                        <Store class="size-4 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ offer.store }}</span>
                                            <span
                                                class="rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                                                :class="offer.official
                                                    ? 'bg-dealytics-cyan/15 text-dealytics-cyan'
                                                    : 'bg-yellow-400/15 text-yellow-400'"
                                            >
                                                {{ offer.official ? 'Officiel' : offer.storeType }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="offer.discount > 0" class="text-[10px] text-dealytics-pink">
                                                -{{ offer.discount }}%
                                            </span>
                                            <span v-if="offer.editionFull" class="text-[10px] text-muted-foreground">
                                                {{ offer.editionFull }}
                                            </span>
                                            <span
                                                v-if="offer.coupon"
                                                class="rounded bg-dealytics-purple/20 px-1 py-0.5 text-[9px] font-medium text-dealytics-purple"
                                            >
                                                Code : {{ offer.coupon.code }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-dealytics-cyan">
                                            {{ offer.price.toFixed(2) }}{{ currencySymbol }}
                                        </div>
                                        <div
                                            v-if="offer.coupon"
                                            class="text-[10px] text-muted-foreground line-through"
                                        >
                                            {{ offer.coupon.priceWithout.toFixed(2) }}{{ currencySymbol }}
                                        </div>
                                    </div>
                                    <ExternalLink class="size-3.5 text-muted-foreground" />
                                </div>
                            </a>
                        </div>
                        <div v-else class="rounded-lg bg-secondary/30 py-8 text-center text-sm text-muted-foreground">
                            Aucune offre pour cette plateforme.
                        </div>

                        <p class="mt-3 text-center text-[10px] text-muted-foreground/60">
                            Les keyshops et marketplaces vendent des clés de revendeurs — prix bas, mais vérifiez la fiabilité du vendeur
                        </p>
                    </div>
                    <div v-else class="border-gradient rounded-xl p-6 text-center">
                        <Store class="mx-auto mb-2 size-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">Aucune offre disponible pour ce jeu actuellement.</p>
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
                                class="w-full gap-2 transition-all duration-200 active:scale-95"
                                :variant="isFavorite ? 'default' : 'outline'"
                                @click="toggleFavorite"
                            >
                                <Heart
                                    class="size-4 transition-all duration-300"
                                    :class="[
                                        isFavorite ? 'fill-white' : '',
                                        heartAnimating ? 'animate-heart-pop' : '',
                                    ]"
                                />
                                {{ isFavorite ? 'Dans vos favoris' : 'Ajouter aux favoris' }}
                            </Button>

                            <!-- Buy button -->
                            <Button
                                v-if="bestOffer?.url"
                                class="w-full gap-2 bg-dealytics-cyan text-dealytics-dark hover:bg-dealytics-cyan/90"
                                as-child
                            >
                                <a
                                    :href="bestOffer.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink class="size-4" />
                                    Acheter sur {{ bestOffer.store }}
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
                                Alerte active : {{ alertPrice }}{{ currencySymbol }}
                            </p>
                            <button
                                class="mt-1 text-[10px] text-muted-foreground hover:text-foreground"
                                @click="clearAlert"
                            >
                                Supprimer l'alerte
                            </button>
                        </div>

                        <div v-else>
                            <div class="flex gap-2">
                                <Input
                                    v-model="alertPrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1000"
                                    placeholder="Prix cible (€)"
                                    class="h-9 text-sm"
                                    @keyup.enter="setAlertPrice"
                                    @input="alertError = ''"
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
                            <p v-if="alertError" class="mt-2 text-xs text-red-400">{{ alertError }}</p>
                        </div>
                    </div>

                    <!-- Game Info -->
                    <div class="border-gradient rounded-xl p-6">
                        <h3 class="mb-4 font-heading text-base font-semibold">Informations</h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Offres disponibles</dt>
                                <dd class="font-medium">{{ nexarda.offerCount }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Magasins</dt>
                                <dd class="font-medium">{{ nexarda.storeCount }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Meilleur prix actuel</dt>
                                <dd class="font-medium text-dealytics-cyan">{{ currentPrice.toFixed(2) }}{{ currencySymbol }}</dd>
                            </div>
                            <div v-if="savingsPercent > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">Réduction max</dt>
                                <dd class="font-medium text-dealytics-pink">-{{ savingsPercent }}%</dd>
                            </div>
                            <!-- RAWG enriched info -->
                            <template v-if="rawg">
                                <div v-if="rawg.metacritic" class="flex justify-between">
                                    <dt class="text-muted-foreground">Metacritic</dt>
                                    <dd class="font-bold" :class="rawg.metacritic >= 75 ? 'text-green-400' : rawg.metacritic >= 50 ? 'text-yellow-400' : 'text-red-400'">
                                        {{ rawg.metacritic }}/100
                                    </dd>
                                </div>
                                <div v-if="rawg.playtime > 0" class="flex justify-between">
                                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                                        <Clock class="size-3" />
                                        Durée moyenne
                                    </dt>
                                    <dd class="font-medium">{{ rawg.playtime }}h</dd>
                                </div>
                                <div v-if="rawg.rating > 0" class="flex justify-between">
                                    <dt class="text-muted-foreground">{{ ratingLabel }}</dt>
                                    <dd class="flex items-center gap-1 font-medium">
                                        <Star class="size-3 fill-yellow-400 text-yellow-400" />
                                        {{ rawg.rating.toFixed(1) }}/5
                                        <span class="text-[10px] text-muted-foreground">({{ rawg.ratings_count }})</span>
                                    </dd>
                                </div>
                                <div v-if="rawg.website" class="flex justify-between">
                                    <dt class="text-muted-foreground">Site officiel</dt>
                                    <dd>
                                        <a :href="rawg.website" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-xs text-dealytics-purple hover:underline">
                                            Visiter
                                            <ExternalLink class="size-2.5" />
                                        </a>
                                    </dd>
                                </div>
                            </template>
                            <!-- RAWG loading skeleton in sidebar -->
                            <template v-else-if="rawgLoading">
                                <div class="flex justify-between">
                                    <div class="h-3 w-20 animate-pulse rounded bg-secondary" />
                                    <div class="h-3 w-12 animate-pulse rounded bg-secondary" />
                                </div>
                                <div class="flex justify-between">
                                    <div class="h-3 w-24 animate-pulse rounded bg-secondary" />
                                    <div class="h-3 w-10 animate-pulse rounded bg-secondary" />
                                </div>
                            </template>

                            <!-- Quality/price score -->
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
                                                            <span class="text-muted-foreground">
                                                                Qualité
                                                                <span v-if="!hasQualityData" class="text-muted-foreground/50">(estimée)</span>
                                                            </span>
                                                            <span class="font-medium">{{ qualityValue }}/100</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-pink transition-all" :style="{ width: `${qualityValue}%` }" />
                                                        </div>
                                                        <div class="flex items-center justify-between text-[11px]">
                                                            <span class="text-muted-foreground">Prix</span>
                                                            <span class="font-medium">{{ priceValue }}/100</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-cyan transition-all" :style="{ width: `${priceValue}%` }" />
                                                        </div>
                                                        <p class="text-[10px] text-muted-foreground/70">
                                                            {{ currentPrice.toFixed(2) }}{{ currencySymbol }} vs {{ originalPrice.toFixed(2) }}{{ currencySymbol }} plein tarif
                                                        </p>
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

<style scoped>
.game-description :deep(h2),
.game-description :deep(h3) {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    font-family: var(--font-heading);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--foreground);
}

.game-description :deep(h2:first-child),
.game-description :deep(h3:first-child) {
    margin-top: 0;
}

.game-description :deep(p) {
    margin-bottom: 0.75rem;
}

.game-description :deep(p:last-child) {
    margin-bottom: 0;
}

.game-description :deep(ul),
.game-description :deep(ol) {
    margin-bottom: 0.75rem;
    margin-left: 1.25rem;
    list-style-type: disc;
}

.game-description :deep(ol) {
    list-style-type: decimal;
}

.game-description :deep(li) {
    margin-bottom: 0.25rem;
}

.game-description :deep(strong),
.game-description :deep(b) {
    font-weight: 500;
    color: var(--foreground);
}
</style>
