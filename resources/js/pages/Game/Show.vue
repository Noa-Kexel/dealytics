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
import { getRememberedHomeListUrl } from '@/composables/useHomeListUrl';
import { vReveal } from '@/directives/reveal';
import type { NexardaGamePrices, NexardaOffer } from '@/lib/nexarda';
import { fetchNexardaGame } from '@/lib/nexarda';
import {
    extractOfferPlatforms,
    offerPlatformLabel,
    parseOfferEdition,
} from '@/lib/platforms';
import { deriveNormalPrice } from '@/lib/price';
import {
    getQualityPriceScore,
    getQualityValue,
    getPriceValue,
    getScoreBorderColor,
    getScoreColor,
    getScoreLabel,
    hasQualityData as checkHasQualityData,
} from '@/lib/qualityPriceScore';

interface SteamData {
    source?: 'steam';
    id: number;
    name: string;
    description: string;
    released: string | null;
    background_image: string | null;
    rating: number;
    ratings_count: number;
    metacritic: number | null;
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
const homeHref = getRememberedHomeListUrl();

const { addAlert, getAlert, removeAlert } = useAlerts();

interface PricePoint {
    date: number;
    price: number;
    store: string;
}

const nexarda = ref<NexardaGamePrices | null>(null);
const loading = ref(true);
const alertPrice = ref('');
const alertSet = ref(false);

// Historique ITAD (série complète) ou snapshots quotidiens.
const priceHistory = ref<PricePoint[]>([]);
const historySource = ref<string | null>(null);
const hasHistory = computed(() => priceHistory.value.length >= 2);

// Plages qui ne réduisent pas réellement les données sont masquées.
const HISTORY_RANGES = [
    { key: '1m', label: '1 mois', days: 31 },
    { key: '3m', label: '3 mois', days: 92 },
    { key: '1y', label: '1 an', days: 366 },
    { key: 'all', label: 'Tout', days: Infinity },
] as const;

type RangeKey = (typeof HISTORY_RANGES)[number]['key'];
const historyRange = ref<RangeKey>('1y');

const dataSpanDays = computed(() => {
    const h = priceHistory.value;

    if (h.length < 2) {
        return 0;
    }

    const sorted = [...h].sort((a, b) => a.date - b.date);

    return (sorted[sorted.length - 1].date - sorted[0].date) / 86400;
});

const availableRanges = computed(() =>
    HISTORY_RANGES.filter((r) => r.days === Infinity || r.days < dataSpanDays.value),
);

const preferredRange = computed<RangeKey>(() =>
    availableRanges.value.some((r) => r.key === '1y') ? '1y' : 'all',
);

const userPickedRange = ref(false);

function selectRange(key: RangeKey) {
    userPickedRange.value = true;
    historyRange.value = key;
}

// Conserve le choix utilisateur si encore valide, sinon bascule sur le défaut.
watch(
    [availableRanges, preferredRange],
    () => {
        const valid = availableRanges.value.some((r) => r.key === historyRange.value);

        if (!userPickedRange.value || !valid) {
            historyRange.value = preferredRange.value;
        }
    },
    { immediate: true },
);

/** Garde les bornes de chaque palier de prix (fonction en escalier). */
function compressSteps(points: PricePoint[]): PricePoint[] {
    if (points.length <= 2) {
        return points;
    }

    const out: PricePoint[] = [points[0]];

    for (let i = 1; i < points.length - 1; i++) {
        if (points[i].price !== out[out.length - 1].price) {
            out.push(points[i]);
        }
    }

    out.push(points[points.length - 1]);

    return out;
}

const visibleHistory = computed(() => {
    const all = [...priceHistory.value].sort((a, b) => a.date - b.date);

    if (all.length === 0) {
        return [];
    }

    const now = Date.now() / 1000;
    const days = HISTORY_RANGES.find((r) => r.key === historyRange.value)?.days ?? Infinity;
    let points = all;

    if (days !== Infinity) {
        const cutoff = now - days * 86400;
        const inRange = all.filter((p) => p.date >= cutoff);
        const before = all.filter((p) => p.date < cutoff).at(-1);

        points = [];

        // Ancre au début de la fenêtre avec le prix alors en vigueur.
        if (before) {
            points.push({ date: cutoff, price: before.price, store: before.store });
        }

        points.push(...inRange);
    }

    // Prolonge jusqu'à maintenant avec le prix live (aligné sur le prix affiché).
    const last = points.at(-1);
    const endPrice = currentPrice.value > 0 ? currentPrice.value : last?.price;

    if (last && endPrice !== undefined) {
        points = [...points, { date: now, price: endPrice, store: 'Maintenant' }];
    }

    return compressSteps(points);
});

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
// « Au plus bas » seulement si le prix a réellement bougé (pas une courbe plate).
const isAtLowest = computed(
    () =>
        hasHistory.value &&
        priceWindow.value.max > priceWindow.value.min &&
        currentPrice.value <= lowestEver.value * 1.02,
);

const steam = ref<SteamData | null>(null);
const steamLoading = ref(false);
const screenshotIndex = ref(0);

const title = computed(() => nexarda.value?.game.name || steam.value?.name || '');
const currencySymbol = computed(() => nexarda.value?.currencySymbol || '€');

const heroImage = computed(
    () => steam.value?.background_image || nexarda.value?.game.cover || '',
);

/** Cover Nexarda (légère) affichée tout de suite pendant le chargement du hero HD. */
const heroPlaceholder = computed(() => nexarda.value?.game.cover || '');

/** True si le hero vient de Steam (paysage) plutôt que d'une cover portrait. */
const hasLandscapeHero = computed(() => !!steam.value?.background_image);

/** Affiche le placeholder jusqu'à ce que le hero HD soit décodé (évite le flash). */
const displayedHero = ref('');
const heroHdReady = ref(false);

watch(
    heroImage,
    (target) => {
        if (!target) {
            displayedHero.value = '';
            heroHdReady.value = false;

            return;
        }

        const isHd = !!steam.value?.background_image && target === steam.value.background_image;
        const placeholder = heroPlaceholder.value;

        if (!isHd) {
            displayedHero.value = target;
            heroHdReady.value = true;

            return;
        }

        // Garde la cover visible pendant le preload HD
        if (placeholder && displayedHero.value !== target) {
            displayedHero.value = placeholder;
            heroHdReady.value = false;
        }

        const preload = new window.Image();
        preload.onload = () => {
            if (heroImage.value === target) {
                displayedHero.value = target;
                heroHdReady.value = true;
            }
        };
        preload.onerror = () => {
            if (heroImage.value === target) {
                displayedHero.value = placeholder || target;
                heroHdReady.value = true;
            }
        };
        preload.src = target;
    },
    { immediate: true },
);

const coverImage = computed(() => nexarda.value?.game.cover || steam.value?.background_image || '');

const bestOffer = computed(() => {
    if (!nexarda.value?.offers.length) {
        return null;
    }

    return nexarda.value.offers.reduce((best, offer) =>
        offer.price < best.price ? offer : best,
    );
});

/** Ouvre l’URL magasin au tap (évite le bug iOS/Android où le lien exige un appui long). */
function openStoreUrl(url: string | null | undefined, event?: Event) {
    if (!url) {
        event?.preventDefault();

        return;
    }

    event?.preventDefault();
    window.open(url, '_blank', 'noopener,noreferrer');
}

const currentPrice = computed(() => nexarda.value?.lowest ?? bestOffer.value?.price ?? 0);
const savingsPercent = computed(() => Math.round(bestOffer.value?.discount ?? nexarda.value?.maxDiscount ?? 0));
const normalPrice = computed(
    () => deriveNormalPrice(currentPrice.value, savingsPercent.value, nexarda.value?.highest) ?? currentPrice.value,
);

function offerPlatformsList(offer: NexardaOffer): string[] {
    return extractOfferPlatforms(offer.editionFull, offer.platform);
}

function offerEditionMeta(offer: NexardaOffer) {
    return parseOfferEdition(offer.editionFull, offer.edition, offer.platform);
}

function platformLabel(slug: string): string {
    return offerPlatformLabel(slug);
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
const offersPage = ref(1);
const OFFERS_PER_PAGE = 10;

const offerPlatforms = computed(() => {
    const counts = new Map<string, number>();

    for (const offer of nexarda.value?.offers ?? []) {
        for (const platform of offerPlatformsList(offer)) {
            counts.set(platform, (counts.get(platform) ?? 0) + 1);
        }
    }

    return Array.from(counts.entries()).sort((a, b) => b[1] - a[1]);
});

const filteredOffers = computed(() => {
    const offers = nexarda.value?.offers ?? [];

    if (selectedOfferPlatform.value === 'all') {
        return offers;
    }

    return offers.filter((offer) =>
        offerPlatformsList(offer).includes(selectedOfferPlatform.value),
    );
});

const offersTotalPages = computed(() =>
    Math.max(1, Math.ceil(filteredOffers.value.length / OFFERS_PER_PAGE)),
);

const paginatedOffers = computed(() => {
    const start = (offersPage.value - 1) * OFFERS_PER_PAGE;

    return filteredOffers.value.slice(start, start + OFFERS_PER_PAGE);
});

const paginatedOfferRows = computed(() =>
    paginatedOffers.value.map((offer) => ({
        offer,
        edition: offerEditionMeta(offer),
    })),
);

const offersPageLabel = computed(() => {
    const total = filteredOffers.value.length;

    if (total === 0) {
        return '';
    }

    const start = (offersPage.value - 1) * OFFERS_PER_PAGE + 1;
    const end = Math.min(offersPage.value * OFFERS_PER_PAGE, total);

    return `${start}–${end} sur ${total}`;
});

/** Numéros de page avec ellipses si trop de pages. */
const offersPageNumbers = computed(() => {
    const total = offersTotalPages.value;
    const current = offersPage.value;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: (number | null)[] = [1];

    const windowStart = Math.max(2, current - 1);
    const windowEnd = Math.min(total - 1, current + 1);

    if (windowStart > 2) {
        pages.push(null);
    }

    for (let page = windowStart; page <= windowEnd; page++) {
        pages.push(page);
    }

    if (windowEnd < total - 1) {
        pages.push(null);
    }

    pages.push(total);

    return pages;
});

const filteredBestOfficialPrice = computed(() => cheapestOffer(filteredOffers.value, true));
const filteredBestKeyshopPrice = computed(() => cheapestOffer(filteredOffers.value, false));

const offersListEl = ref<HTMLElement | null>(null);

function goToOffersPage(page: number): void {
    const next = Math.min(Math.max(1, page), offersTotalPages.value);

    if (next === offersPage.value) {
        return;
    }

    offersPage.value = next;
    offersListEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

watch(
    () => nexarda.value?.offers,
    () => {
        selectedOfferPlatform.value = 'all';
        offersPage.value = 1;
    },
);

watch(selectedOfferPlatform, () => {
    offersPage.value = 1;
});

watch(offersTotalPages, (total) => {
    if (offersPage.value > total) {
        offersPage.value = total;
    }
});

const hasQualityData = computed(() =>
    checkHasQualityData(steam.value?.metacritic, steam.value?.rating),
);
const qualityValue = computed(() =>
    getQualityValue(steam.value?.metacritic, steam.value?.rating),
);
// Prix hors promo = offre la plus chère actuelle, sinon prix dérivé de la remise.
const originalPrice = computed(() =>
    Math.max(nexarda.value?.highest ?? 0, normalPrice.value),
);
const priceValue = computed(() => getPriceValue(currentPrice.value, originalPrice.value));
const qualityPriceScore = computed(() =>
    getQualityPriceScore(
        priceValue.value,
        steam.value?.metacritic,
        steam.value?.rating,
    ),
);
const scoreColor = computed(() => getScoreColor(qualityPriceScore.value));
const scoreBorderColor = computed(() => getScoreBorderColor(qualityPriceScore.value));
const scoreLabel = computed(() => getScoreLabel(qualityPriceScore.value));

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

    if (!currentPrice.value || currentPrice.value <= 0) {
        alertError.value = 'Prix actuel indisponible pour définir une alerte.';

        return;
    }

    if (value >= currentPrice.value) {
        alertError.value = `Le prix cible doit être inférieur au prix actuel (${currentPrice.value.toFixed(2)}${currencySymbol.value}).`;

        return;
    }

    if (value > 1000) {
        alertError.value = 'Prix trop élevé (max 1000€).';

        return;
    }

    alertError.value = '';

    try {
        await addAlert(
            gameId,
            title.value,
            Math.round(value * 100) / 100,
            currentPrice.value,
        );
        alertSet.value = true;
    } catch (error) {
        alertError.value = error instanceof Error
            ? error.message
            : 'Impossible d’enregistrer l’alerte.';
    }
}

function clearAlert() {
    removeAlert(gameId);
    alertSet.value = false;
    alertPrice.value = '';
    alertError.value = '';
}

function prevScreenshot() {
    if (steam.value && steam.value.screenshots.length > 0) {
        screenshotIndex.value = (screenshotIndex.value - 1 + steam.value.screenshots.length) % steam.value.screenshots.length;
    }
}

function nextScreenshot() {
    if (steam.value && steam.value.screenshots.length > 0) {
        screenshotIndex.value = (screenshotIndex.value + 1) % steam.value.screenshots.length;
    }
}

const steamReleaseDate = computed(() => {
    if (!steam.value?.released) {
        return null;
    }

    const parsed = new Date(steam.value.released);

    if (Number.isNaN(parsed.getTime())) {
        return steam.value.released;
    }

    return parsed.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const shortDescription = ref(true);
const descriptionIsLong = computed(() => (steam.value?.description?.length ?? 0) > 400);

onMounted(async () => {
    loadFavorites();

    try {
        nexarda.value = await fetchNexardaGame(gameId);

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
            // historique optionnel
        }

        const existingAlert = getAlert(gameId);

        if (existingAlert) {
            alertPrice.value = (existingAlert.target_price ?? existingAlert.targetPrice ?? '').toString();
            alertSet.value = true;
        }

        if (nexarda.value?.game.name) {
            steamLoading.value = true;

            try {
                const steamResponse = await fetch(`/api/steam/${encodeURIComponent(nexarda.value.game.name)}`);

                if (steamResponse.ok) {
                    steam.value = await steamResponse.json();
                }
            } catch {
                // enrichissement Steam optionnel
            }

            steamLoading.value = false;
        }
    } catch {
        // échec chargement principal
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="title || 'Chargement...'" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 pb-28 lg:px-6 lg:pb-6">
        <Link
            :href="homeHref"
            class="mb-6 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Retour à l'accueil
        </Link>
        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <div class="size-8 animate-spin rounded-full border-2 border-dealytics-purple border-t-transparent" />
            <p class="mt-4 text-sm text-muted-foreground">Chargement du jeu...</p>
        </div>

        <template v-else-if="nexarda">
            <div class="relative mb-8 overflow-hidden rounded-2xl border-gradient-strong">
                <div class="relative bg-secondary/40">
                    <!-- Cover légère en fond pendant le chargement HD -->
                    <GameImage
                        v-if="heroPlaceholder && (!heroHdReady || !hasLandscapeHero)"
                        :src="heroPlaceholder"
                        alt=""
                        aria-hidden="true"
                        eager
                        class="absolute inset-0 size-full scale-110 object-cover opacity-50 blur-2xl"
                    />
                    <GameImage
                        :src="displayedHero || heroPlaceholder"
                        :alt="title"
                        eager
                        class="absolute inset-0 size-full transition-opacity duration-500"
                        :class="[
                            hasLandscapeHero && heroHdReady ? 'object-cover' : 'object-contain object-center',
                            heroHdReady || !hasLandscapeHero ? 'opacity-100' : 'opacity-70',
                        ]"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/55 to-black/10 md:via-black/50 md:to-transparent" />
                    <div
                        class="relative flex min-h-60 flex-col justify-end p-5 sm:min-h-72 sm:p-6 md:min-h-0 md:p-8"
                        :class="hasLandscapeHero ? 'md:aspect-[21/9]' : 'md:aspect-[16/9]'"
                    >
                        <h1 class="font-heading text-2xl font-bold text-white drop-shadow-[0_2px_10px_rgba(0,0,0,0.85)] sm:text-3xl md:text-4xl">
                            {{ title }}
                        </h1>
                        <div v-if="steam" class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                v-for="genre in steam.genres"
                                :key="genre"
                                class="rounded-full border border-dealytics-purple/60 bg-black/70 px-2.5 py-0.5 text-[10px] font-semibold text-white backdrop-blur-md"
                            >
                                {{ genre }}
                            </span>
                            <span v-if="steamReleaseDate" class="flex items-center gap-1 rounded-full bg-black/70 px-2 py-0.5 text-[10px] font-medium text-white backdrop-blur-md">
                                <Calendar class="size-2.5" />
                                {{ steamReleaseDate }}
                            </span>
                            <span v-if="steam.rating > 0" class="flex items-center gap-1 rounded-full border border-dealytics-cyan/50 bg-black/70 px-2 py-0.5 text-[10px] font-semibold text-dealytics-cyan backdrop-blur-md">
                                <Star class="size-2.5 fill-dealytics-cyan" />
                                {{ steam.rating.toFixed(1) }}/5
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <DealBadge
                                :current-price="currentPrice"
                                :lowest-price="lowestEver"
                                :savings="savingsPercent"
                                :at-lowest="isAtLowest"
                            />

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
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="min-w-0 space-y-6 lg:col-span-2">
                    <div v-if="steamLoading" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 h-5 w-40 animate-pulse rounded bg-secondary" />
                        <div class="space-y-2">
                            <div class="h-3 w-full animate-pulse rounded bg-secondary" />
                            <div class="h-3 w-5/6 animate-pulse rounded bg-secondary" />
                            <div class="h-3 w-4/6 animate-pulse rounded bg-secondary" />
                        </div>
                    </div>
                    <div v-else-if="steam?.description" v-reveal class="border-gradient rounded-xl p-6">
                        <div class="mb-3 flex items-center gap-2">
                            <Gamepad2 class="size-4 text-dealytics-purple" />
                            <h2 class="font-heading text-lg font-semibold">À propos</h2>
                        </div>
                        <div class="relative">
                            <div
                                class="game-description text-sm leading-relaxed text-muted-foreground"
                                :class="shortDescription && descriptionIsLong ? 'max-h-48 overflow-hidden' : ''"
                                v-html="steam.description"
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
                        <div v-if="steam.platforms.length || steam.developers.length" class="mt-4 flex flex-wrap gap-4 border-t border-border/50 pt-4">
                            <div v-if="steam.platforms.length" class="flex items-start gap-2">
                                <Monitor class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="p in steam.platforms"
                                        :key="p"
                                        class="rounded bg-secondary/80 px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ p }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="steam.developers.length" class="flex items-start gap-2">
                                <Code class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <span class="text-xs text-muted-foreground">{{ steam.developers.join(', ') }}</span>
                            </div>
                        </div>
                        <div v-if="steam.tags.length" class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in steam.tags"
                                :key="tag"
                                class="flex items-center gap-1 rounded-full bg-secondary/60 px-2 py-0.5 text-[10px] text-muted-foreground"
                            >
                                <Tag class="size-2" />
                                {{ tag }}
                            </span>
                        </div>
                    </div>
                    <div v-if="steamLoading" class="border-gradient rounded-xl p-6">
                        <div class="mb-3 h-5 w-32 animate-pulse rounded bg-secondary" />
                        <div class="aspect-video w-full animate-pulse rounded-lg bg-secondary" />
                    </div>
                    <div v-else-if="steam?.screenshots?.length" v-reveal class="border-gradient rounded-xl p-6">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ImageIcon class="size-4 text-dealytics-cyan" />
                                <h2 class="font-heading text-lg font-semibold">Screenshots</h2>
                                <span class="text-xs text-muted-foreground">({{ screenshotIndex + 1 }}/{{ steam.screenshots.length }})</span>
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
                                    :src="steam.screenshots[screenshotIndex]"
                                    :alt="`Screenshot ${screenshotIndex + 1}`"
                                    class="aspect-video w-full object-cover"
                                />
                            </Transition>
                        </div>
                        <div v-if="steam.screenshots.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                            <button
                                v-for="(url, idx) in steam.screenshots"
                                :key="idx"
                                class="shrink-0 overflow-hidden rounded-md border-2 transition-all"
                                :class="idx === screenshotIndex ? 'border-dealytics-cyan opacity-100' : 'border-transparent opacity-50 hover:opacity-75'"
                                @click="screenshotIndex = idx"
                            >
                                <img :src="url" :alt="`Thumb ${idx + 1}`" class="h-12 w-20 object-cover" />
                            </button>
                        </div>
                    </div>
                    <div v-reveal class="border-gradient rounded-xl p-6">
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
                        <div v-if="hasHistory && availableRanges.length > 1" class="mb-4 flex flex-wrap gap-1.5">
                            <button
                                v-for="r in availableRanges"
                                :key="r.key"
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                :class="historyRange === r.key
                                    ? 'bg-dealytics-cyan/15 text-dealytics-cyan'
                                    : 'bg-secondary/60 text-muted-foreground hover:text-foreground'"
                                @click="selectRange(r.key)"
                            >
                                {{ r.label }}
                            </button>
                        </div>

                        <PriceHistoryChart
                            v-if="hasHistory"
                            :price-history="visibleHistory"
                            :current-price="currentPrice"
                            :currency-symbol="currencySymbol"
                            :range-key="historyRange"
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
                        <p
                            v-if="isAtLowest"
                            class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-dealytics-pink"
                        >
                            <Flame class="size-3.5" />
                            Le prix actuel est au plus bas observé depuis le début du suivi.
                        </p>
                    </div>
                    <div v-if="filteredOffers.length > 1" v-reveal class="border-gradient rounded-xl p-6">
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
                    <div v-if="nexarda.offers.length" v-reveal class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Store class="size-4 text-dealytics-pink" />
                                <h2 class="font-heading text-lg font-semibold">
                                    Comparer les prix ({{ nexarda.offerCount }} offres, {{ nexarda.storeCount }} stores)
                                </h2>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <span>via</span>
                                <span class="font-medium text-dealytics-pink">NEXARDA</span>
                            </div>
                        </div>
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
                        <div v-if="filteredOffers.length" ref="offersListEl" class="space-y-2 scroll-mt-24">
                            <a
                                v-for="({ offer, edition }, idx) in paginatedOfferRows"
                                :key="`${offer.store}-${offer.price}-${idx}`"
                                :href="offer.url || undefined"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex touch-manipulation items-center justify-between gap-3 rounded-lg bg-secondary/50 p-3 transition-colors hover:bg-secondary"
                                @click="openStoreUrl(offer.url, $event)"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <img
                                        v-if="offer.storeImage"
                                        :src="offer.storeImage"
                                        :alt="offer.store"
                                        class="size-8 shrink-0 rounded-lg object-contain"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                    />
                                    <div v-else class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-background">
                                        <Store class="size-4 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-sm font-medium">{{ offer.store }}</span>
                                            <span
                                                class="shrink-0 whitespace-nowrap rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                                                :class="offer.official
                                                    ? 'bg-dealytics-cyan/15 text-dealytics-cyan'
                                                    : 'bg-yellow-400/15 text-yellow-400'"
                                            >
                                                {{ offer.official ? 'Officiel' : offer.storeType }}
                                            </span>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span v-if="offer.discount > 0" class="whitespace-nowrap text-[10px] text-dealytics-pink">
                                                -{{ offer.discount }}%
                                            </span>
                                            <span
                                                v-if="edition.label"
                                                class="min-w-0 truncate text-[10px] text-muted-foreground"
                                            >
                                                {{ edition.label }}
                                            </span>
                                            <span
                                                v-for="platform in edition.platforms"
                                                :key="platform"
                                                class="shrink-0 whitespace-nowrap rounded-full bg-secondary px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground"
                                            >
                                                {{ platformLabel(platform) }}
                                            </span>
                                            <span
                                                v-if="offer.coupon"
                                                class="shrink-0 whitespace-nowrap rounded bg-dealytics-purple/20 px-1 py-0.5 text-[9px] font-medium text-dealytics-purple"
                                            >
                                                Code : {{ offer.coupon.code }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-dealytics-cyan">
                                            {{ offer.price.toFixed(2) }}{{ currencySymbol }}
                                        </div>
                                        <div
                                            v-if="offer.coupon"
                                            class="whitespace-nowrap text-[10px] text-muted-foreground line-through"
                                        >
                                            {{ offer.coupon.priceWithout.toFixed(2) }}{{ currencySymbol }}
                                        </div>
                                    </div>
                                    <ExternalLink class="size-3.5 shrink-0 text-muted-foreground" />
                                </div>
                            </a>
                        </div>
                        <div v-else class="rounded-lg bg-secondary/30 py-8 text-center text-sm text-muted-foreground">
                            Aucune offre pour cette plateforme.
                        </div>
                        <div
                            v-if="offersTotalPages > 1"
                            class="mt-4 flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ offersPageLabel }}
                            </p>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border border-border/50 bg-secondary/30 text-muted-foreground transition-colors hover:border-dealytics-purple/40 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                    :disabled="offersPage <= 1"
                                    aria-label="Page précédente"
                                    @click="goToOffersPage(offersPage - 1)"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <template v-for="(pageNum, pageIdx) in offersPageNumbers" :key="pageNum ?? `ellipsis-${pageIdx}`">
                                    <span
                                        v-if="pageNum === null"
                                        class="inline-flex size-8 items-center justify-center text-xs text-muted-foreground"
                                        aria-hidden="true"
                                    >
                                        …
                                    </span>
                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                                        :class="offersPage === pageNum
                                            ? 'border-dealytics-purple bg-dealytics-purple/20 text-dealytics-purple'
                                            : 'border-border/50 bg-secondary/30 text-muted-foreground hover:border-dealytics-purple/40 hover:text-foreground'"
                                        :aria-current="offersPage === pageNum ? 'page' : undefined"
                                        :aria-label="`Page ${pageNum}`"
                                        @click="goToOffersPage(pageNum)"
                                    >
                                        {{ pageNum }}
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border border-border/50 bg-secondary/30 text-muted-foreground transition-colors hover:border-dealytics-purple/40 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                    :disabled="offersPage >= offersTotalPages"
                                    aria-label="Page suivante"
                                    @click="goToOffersPage(offersPage + 1)"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-center text-[10px] text-muted-foreground/60">
                            Les keyshops et marketplaces vendent des clés de revendeurs (prix bas, mais vérifiez la fiabilité du vendeur)
                        </p>
                    </div>
                    <div v-else v-reveal class="border-gradient rounded-xl p-6 text-center">
                        <Store class="mx-auto mb-2 size-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">Aucune offre disponible pour ce jeu actuellement.</p>
                    </div>
                </div>
                <div class="min-w-0 space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <div v-reveal class="border-gradient rounded-xl p-6">
                        <h3 class="mb-4 font-heading text-base font-semibold">Actions</h3>

                        <div class="space-y-3">
                            <Button
                                :class="[
                                    'w-full gap-2 transition-all duration-200 active:scale-95',
                                    !isFavorite && 'hover:border-dealytics-purple/50 hover:bg-dealytics-purple/10 hover:text-dealytics-purple dark:hover:border-dealytics-purple/50 dark:hover:bg-dealytics-purple/10 dark:hover:text-dealytics-purple',
                                ]"
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
                            <a
                                v-if="bestOffer?.url"
                                :href="bestOffer.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex h-9 w-full touch-manipulation items-center justify-center gap-2 rounded-md bg-dealytics-cyan px-4 py-2 text-sm font-medium text-dealytics-dark transition-all hover:bg-dealytics-cyan/90"
                                @click="openStoreUrl(bestOffer.url, $event)"
                            >
                                <ExternalLink class="size-4" />
                                Acheter sur {{ bestOffer.store }}
                            </a>
                        </div>
                    </div>
                    <div v-reveal="{ delay: 90 }" class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <Bell class="size-4 text-dealytics-cyan" />
                            <h3 class="font-heading text-base font-semibold">Alerte de prix</h3>
                        </div>

                        <p class="mb-3 text-xs text-muted-foreground">
                            Définissez un prix cible
                            <span v-if="currentPrice > 0">
                                inférieur à {{ currentPrice.toFixed(2) }}{{ currencySymbol }}
                            </span>
                            et soyez notifié quand il est atteint.
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
                                    min="0.01"
                                    :max="currentPrice > 0 ? (currentPrice - 0.01).toFixed(2) : undefined"
                                    placeholder="Prix cible (€)"
                                    class="h-9 text-sm"
                                    @keyup.enter="setAlertPrice"
                                    @input="alertError = ''"
                                />
                                <Button
                                    size="sm"
                                    class="shrink-0 bg-dealytics-purple hover:bg-dealytics-deep-purple"
                                    :disabled="!alertPrice || currentPrice <= 0"
                                    @click="setAlertPrice"
                                >
                                    <Bell class="size-3.5" />
                                </Button>
                            </div>
                            <p v-if="alertError" class="mt-2 text-xs text-red-400">{{ alertError }}</p>
                        </div>
                    </div>
                    <div v-reveal="{ delay: 180 }" class="border-gradient rounded-xl p-6">
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
                            <template v-if="steam">
                                <div v-if="steam.metacritic" class="flex justify-between">
                                    <dt class="text-muted-foreground">Metacritic</dt>
                                    <dd class="font-bold" :class="steam.metacritic >= 75 ? 'text-green-400' : steam.metacritic >= 50 ? 'text-yellow-400' : 'text-red-400'">
                                        {{ steam.metacritic }}/100
                                    </dd>
                                </div>
                                <div v-if="steam.rating > 0" class="flex justify-between">
                                    <dt class="text-muted-foreground">Note Steam</dt>
                                    <dd class="flex items-center gap-1 font-medium">
                                        <Star class="size-3 fill-yellow-400 text-yellow-400" />
                                        {{ steam.rating.toFixed(1) }}/5
                                        <span class="text-[10px] text-muted-foreground">({{ steam.ratings_count }})</span>
                                    </dd>
                                </div>
                                <div v-if="steam.website" class="flex justify-between">
                                    <dt class="text-muted-foreground">Site officiel</dt>
                                    <dd>
                                        <a :href="steam.website" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-xs text-dealytics-purple hover:underline">
                                            Visiter
                                            <ExternalLink class="size-2.5" />
                                        </a>
                                    </dd>
                                </div>
                            </template>
                            <template v-else-if="steamLoading">
                                <div class="flex justify-between">
                                    <div class="h-3 w-20 animate-pulse rounded bg-secondary" />
                                    <div class="h-3 w-12 animate-pulse rounded bg-secondary" />
                                </div>
                                <div class="flex justify-between">
                                    <div class="h-3 w-24 animate-pulse rounded bg-secondary" />
                                    <div class="h-3 w-10 animate-pulse rounded bg-secondary" />
                                </div>
                            </template>
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

            <div
                class="fixed inset-x-0 bottom-0 z-40 border-t border-border/50 bg-background/90 px-4 pt-3 backdrop-blur-xl lg:hidden"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
            >
                <div class="mx-auto flex max-w-7xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[10px] text-muted-foreground">
                            {{ bestOffer?.store ? `Meilleur sur ${bestOffer.store}` : 'Meilleur prix' }}
                        </p>
                        <p class="truncate text-lg font-bold text-dealytics-cyan tabular-nums">
                            {{ currentPrice === 0 ? '—' : `${currentPrice.toFixed(2)}${currencySymbol}` }}
                        </p>
                    </div>
                    <Button
                        size="icon"
                        :variant="isFavorite ? 'default' : 'outline'"
                        class="size-11 shrink-0 transition-all duration-200 active:scale-95"
                        :aria-label="isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
                        @click="toggleFavorite"
                    >
                        <Heart
                            class="size-4 transition-all duration-300"
                            :class="[
                                isFavorite ? 'fill-white' : '',
                                heartAnimating ? 'animate-heart-pop' : '',
                            ]"
                        />
                    </Button>
                    <a
                        v-if="bestOffer?.url"
                        :href="bestOffer.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-11 min-w-0 flex-[1.2] touch-manipulation items-center justify-center gap-2 rounded-md bg-dealytics-cyan px-4 text-sm font-semibold text-dealytics-dark transition-all active:scale-[0.98] hover:bg-dealytics-cyan/90"
                        @click="openStoreUrl(bestOffer.url, $event)"
                    >
                        <ExternalLink class="size-4 shrink-0" />
                        <span class="truncate">Acheter</span>
                    </a>
                </div>
            </div>
        </template>
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <Star class="mb-4 size-16 text-muted-foreground/20" />
            <h3 class="font-heading text-lg font-semibold">Jeu introuvable</h3>
            <p class="mt-1 text-sm text-muted-foreground">Ce jeu n'existe pas ou n'est plus disponible.</p>
            <Link :href="homeHref" class="mt-4 text-sm text-dealytics-purple hover:underline">
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
