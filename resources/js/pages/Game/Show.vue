<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Heart,
    Bell,
    Star,
    Store,
    Calendar,
    Gamepad2,
    Monitor,
    Code,
    Tag,
    Clock,
    ChevronLeft,
    ChevronRight,
    BarChart3,
    Image as ImageIcon,
} from 'lucide-vue-next';
import { ref, onMounted, computed, nextTick } from 'vue';
import DealBadge from '@/components/DealBadge.vue';
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

interface NexardaOffer {
    url: string | null;
    store: string;
    storeImage: string | null;
    storeType: string;
    official: boolean;
    edition: string | null;
    editionFull: string | null;
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

const nexarda = ref<NexardaData | null>(null);
const loading = ref(true);
const alertPrice = ref('');
const alertSet = ref(false);

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

const bestOfficialPrice = computed(() => nexarda.value?.offers.find((o) => o.official) ?? null);
const bestKeyshopPrice = computed(() => nexarda.value?.offers.find((o) => !o.official) ?? null);

// Quality/price score (/100) — combines the game's quality (RAWG metacritic
// or rating, with a neutral fallback when RAWG is unavailable) and the current
// discount. No fabricated data: both inputs are real when present.
const hasQualityData = computed(() => !!(rawg.value?.metacritic || rawg.value?.rating));
const qualityValue = computed(() => {
    if (rawg.value?.metacritic) {
        return rawg.value.metacritic;
    }

    if (rawg.value?.rating) {
        return Math.round(rawg.value.rating * 20);
    }

    return 60; // neutral fallback
});
const savingsScore = computed(() => Math.min(Math.round(savingsPercent.value * 1.5), 100));
const qualityPriceScore = computed(() => Math.round(qualityValue.value * 0.6 + savingsScore.value * 0.4));

const scoreColor = computed(() => {
    if (qualityPriceScore.value >= 75) {
        return 'text-dealytics-cyan';
    }

    if (qualityPriceScore.value >= 55) {
        return 'text-dealytics-purple';
    }

    if (qualityPriceScore.value >= 35) {
        return 'text-yellow-400';
    }

    return 'text-muted-foreground';
});

const scoreBorderColor = computed(() => {
    if (qualityPriceScore.value >= 75) {
        return 'border-dealytics-cyan/50 bg-dealytics-cyan/10';
    }

    if (qualityPriceScore.value >= 55) {
        return 'border-dealytics-purple/50 bg-dealytics-purple/10';
    }

    if (qualityPriceScore.value >= 35) {
        return 'border-yellow-400/50 bg-yellow-400/10';
    }

    return 'border-border bg-secondary/50';
});

const scoreLabel = computed(() => {
    if (qualityPriceScore.value >= 75) {
        return 'Excellent';
    }

    if (qualityPriceScore.value >= 55) {
        return 'Bon deal';
    }

    if (qualityPriceScore.value >= 35) {
        return 'Moyen';
    }

    return 'Faible';
});

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
async function setAlertPrice() {
    if (!alertPrice.value) {
        return;
    }

    await addAlert(gameId, title.value, parseFloat(alertPrice.value));
    alertSet.value = true;
}

function clearAlert() {
    removeAlert(gameId);
    alertSet.value = false;
    alertPrice.value = '';
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

const rawgReleaseDate = computed(() => {
    if (!rawg.value?.released) {
        return null;
    }

    return new Date(rawg.value.released).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

// Truncated description
const shortDescription = ref(true);
const descriptionText = computed(() => {
    if (!rawg.value?.description) {
        return '';
    }

    if (shortDescription.value && rawg.value.description.length > 300) {
        return rawg.value.description.slice(0, 300) + '...';
    }

    return rawg.value.description;
});

onMounted(async () => {
    loadFavorites();

    try {
        // Primary data: Nexarda prices by game id
        const response = await fetch(`/api/nexarda/game/${gameId}`);

        if (response.ok) {
            nexarda.value = await response.json();
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
                    <img
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
                                :lowest-price="currentPrice"
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
                        <p class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{{ descriptionText }}</p>
                        <button
                            v-if="rawg.description.length > 300"
                            class="mt-2 text-xs font-medium text-dealytics-purple hover:underline"
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

                    <!-- Price comparison chart (real per-store prices) -->
                    <div v-if="nexarda.offers.length > 1" class="border-gradient rounded-xl p-6">
                        <div class="mb-4 flex items-center gap-2">
                            <BarChart3 class="size-4 text-dealytics-purple" />
                            <h2 class="font-heading text-lg font-semibold">Comparaison des prix par magasin</h2>
                        </div>

                        <StorePriceChart
                            :offers="nexarda.offers"
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

                        <!-- Best official vs best keyshop summary -->
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-dealytics-cyan/20 bg-dealytics-cyan/5 p-3 text-center">
                                <div class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">Store officiel</div>
                                <div v-if="bestOfficialPrice" class="mt-1 text-xl font-bold text-dealytics-cyan">
                                    {{ bestOfficialPrice.price.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div v-else class="mt-1 text-sm text-muted-foreground">Indisponible</div>
                                <div v-if="bestOfficialPrice" class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ bestOfficialPrice.store }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-yellow-400/20 bg-yellow-400/5 p-3 text-center">
                                <div class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">Keyshop / Marketplace</div>
                                <div v-if="bestKeyshopPrice" class="mt-1 text-xl font-bold text-yellow-400">
                                    {{ bestKeyshopPrice.price.toFixed(2) }}{{ currencySymbol }}
                                </div>
                                <div v-else class="mt-1 text-sm text-muted-foreground">Indisponible</div>
                                <div v-if="bestKeyshopPrice" class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ bestKeyshopPrice.store }}
                                </div>
                            </div>
                        </div>

                        <!-- Full offer list -->
                        <div class="space-y-2">
                            <a
                                v-for="(offer, idx) in nexarda.offers"
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

                        <div v-else class="flex gap-2">
                            <Input
                                v-model="alertPrice"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Prix cible (€)"
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
                                    <dt class="text-muted-foreground">Note RAWG</dt>
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
                                                            <span class="text-muted-foreground">Réduction</span>
                                                            <span class="font-medium">{{ savingsPercent }}%</span>
                                                        </div>
                                                        <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                            <div class="h-full rounded-full bg-dealytics-cyan transition-all" :style="{ width: `${savingsScore}%` }" />
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
