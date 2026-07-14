<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search,
    SlidersHorizontal,
    Flame,
    TrendingDown,
    Zap,
    Gamepad2,
    Bell,
    LineChart,
    Store,
    Star,
    ArrowRight,
    PlugZap,
    RefreshCw,
} from 'lucide-vue-next';
import { onMounted, onBeforeUnmount } from 'vue';
import { ref, computed } from 'vue';
import GameCard from '@/components/GameCard.vue';
import GameCardSkeleton from '@/components/GameCardSkeleton.vue';
import GameImage from '@/components/GameImage.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFavorites } from '@/composables/useFavorites';
import { vReveal } from '@/directives/reveal';

interface GameItem {
    id: string;
    title: string;
    image: string | null;
    price: number | null;
    normalPrice: number | null;
    discount: number;
    upcoming: boolean;
    platforms: string[];
}

interface GamesResponse {
    games: GameItem[];
    page: number;
    pages: number;
    total: number;
}

const features = [
    {
        icon: Bell,
        title: 'Alertes prix',
        description: 'Notification lorsque vos jeux sont en promotion',
    },
    {
        icon: LineChart,
        title: 'Historique des prix',
        description: 'Trouvez le meilleur moment pour acheter',
    },
    {
        icon: Store,
        title: 'Plusieurs magasins',
        description: 'Comparez sur toutes les principales plateformes',
    },
    {
        icon: Star,
        title: 'Score Qualité/Prix',
        description: 'Notre algorithme exclusif évalue chaque deal',
    },
];

const PLATFORMS = [
    { slug: 'steam', name: 'Steam' },
    { slug: 'gog', name: 'GOG' },
    { slug: 'epic-games-launcher', name: 'Epic Games' },
    { slug: 'xbox', name: 'Xbox' },
    { slug: 'playstation', name: 'PlayStation' },
    { slug: 'nintendo', name: 'Nintendo' },
];

const searchQuery = ref('');
const games = ref<GameItem[]>([]);
const loading = ref(false);
const loadingMore = ref(false);
const hasSearched = ref(false);
const loadError = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const selectedPlatform = ref<string>('all');
const maxPrice = ref<string>('all');
const sortBy = ref<string>('popular');
const onSaleOnly = ref(false);

const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);

const { loadFavorites } = useFavorites();

onMounted(() => {
    loadFavorites();
    loadGames();
});

// Client-side sort + filter over the fetched pages (Nexarda's API ignores
// sort/filter params, so we apply them here).
const displayedGames = computed(() => {
    let list = games.value.filter((g) => g.price !== null);

    if (selectedPlatform.value !== 'all') {
        list = list.filter((g) => g.platforms.includes(selectedPlatform.value));
    }

    if (maxPrice.value !== 'all') {
        const cap = parseFloat(maxPrice.value);
        list = list.filter((g) => (g.price ?? Infinity) <= cap);
    }

    if (onSaleOnly.value) {
        list = list.filter((g) => g.discount > 0);
    }

    const sorted = [...list];

    switch (sortBy.value) {
        case 'price':
            sorted.sort((a, b) => (a.price ?? 0) - (b.price ?? 0));
            break;
        case 'savings':
            sorted.sort((a, b) => b.discount - a.discount);
            break;
        case 'title':
            sorted.sort((a, b) => a.title.localeCompare(b.title));
            break;
        // 'popular' keeps the API's popularity order
    }

    return sorted;
});

const hasMore = computed(() => currentPage.value < totalPages.value);

// Stats
const gamesTracked = computed(() => games.value.filter((g) => g.price !== null).length);
const hotDeals = computed(() => games.value.filter((g) => g.discount > 50).length);
const totalSavings = computed(() =>
    Math.round(
        games.value.reduce(
            (acc, g) => acc + ((g.normalPrice ?? 0) - (g.price ?? 0)),
            0,
        ),
    ),
);

async function fetchGames(page: number): Promise<GamesResponse> {
    const params = new URLSearchParams();

    if (searchQuery.value.trim()) {
        params.set('q', searchQuery.value.trim());
    }

    params.set('page', String(page));

    const response = await fetch(`/api/games?${params.toString()}`);

    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }

    return response.json();
}

async function loadGames() {
    loading.value = true;
    hasSearched.value = true;
    loadError.value = false;
    currentPage.value = 1;

    try {
        const data = await fetchGames(1);
        games.value = data.games;
        totalPages.value = data.pages;
    } catch {
        games.value = [];
        totalPages.value = 1;
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value) {
        return;
    }

    loadingMore.value = true;
    currentPage.value++;

    try {
        const data = await fetchGames(currentPage.value);
        games.value = [...games.value, ...data.games];
        totalPages.value = data.pages;
    } catch {
        currentPage.value--;
    } finally {
        loadingMore.value = false;
    }
}

// ── Autocomplete ────────────────────────────────────────────
// Suggestions reuse the games already fetched — no extra request.
const showSuggestions = ref(false);
const activeSuggestion = ref(-1);
const searchContainer = ref<HTMLElement | null>(null);

const suggestions = computed(() => displayedGames.value.slice(0, 6));

// Debounced search (re-queries the API by title)
function onSearchInput() {
    showSuggestions.value = searchQuery.value.trim().length > 0;
    activeSuggestion.value = -1;

    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }

    debounceTimer.value = setTimeout(() => {
        loadGames();
    }, 350);
}

function selectSuggestion(game: GameItem) {
    closeSuggestions();
    router.visit(`/game/${game.id}`);
}

function onSearchEnter() {
    const active = suggestions.value[activeSuggestion.value];

    if (showSuggestions.value && active) {
        selectSuggestion(active);

        return;
    }

    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }

    closeSuggestions();
    loadGames();
}

function moveActive(delta: number) {
    if (!showSuggestions.value || suggestions.value.length === 0) {
        return;
    }

    const count = suggestions.value.length;
    activeSuggestion.value = (activeSuggestion.value + delta + count) % count;
}

function closeSuggestions() {
    showSuggestions.value = false;
    activeSuggestion.value = -1;
}

function onSearchFocus() {
    if (searchQuery.value.trim() && suggestions.value.length > 0) {
        showSuggestions.value = true;
    }
}

function onDocumentMousedown(event: MouseEvent) {
    if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
        closeSuggestions();
    }
}

onMounted(() => document.addEventListener('mousedown', onDocumentMousedown));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentMousedown));

// Sort/filter changes are purely client-side — no refetch needed.
</script>

<template>
    <Head title="Accueil" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Hero Section -->
        <div
            class="border-gradient-strong relative mb-8 overflow-hidden rounded-2xl p-8 md:p-12"
        >
            <!-- Background glow effects -->
            <div
                class="bg-glow-purple pointer-events-none absolute -top-24 -right-24 size-96 opacity-50"
            />
            <div
                class="bg-glow-cyan pointer-events-none absolute -bottom-24 -left-24 size-96 opacity-30"
            />

            <div
                class="pointer-events-none absolute inset-y-0 right-0 hidden w-[52%] lg:block xl:w-[50%]"
            >
                <img
                    src="/images/hero.png"
                    alt="Gamer avec casque et setup gaming"
                    class="hero-image-fade h-full w-full object-contain object-right"
                    loading="eager"
                />
            </div>

            <div class="relative z-10 max-w-2xl lg:max-w-[58%]">
                    <h1
                        class="font-heading text-4xl leading-tight font-extrabold md:text-5xl lg:text-6xl"
                    >
                        Trouvez les Meilleures
                        <br />
                        <span class="text-gradient-hero">Offres Gaming</span>
                    </h1>

                    <p
                        class="mt-4 max-w-xl text-base text-muted-foreground md:text-lg"
                    >
                        Comparez les prix sur toutes les plateformes. Soyez
                        alerté quand vos jeux préférés atteignent leur prix le
                        plus bas.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-6 md:gap-10">
                        <div class="flex items-center gap-2">
                            <Zap class="size-4 text-dealytics-purple" />
                            <div>
                                <span
                                    class="text-lg font-bold text-dealytics-purple"
                                    >{{ gamesTracked }}+</span
                                >
                                <span class="ml-1 text-xs text-muted-foreground"
                                    >Jeux suivis</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Flame class="size-4 text-dealytics-pink" />
                            <div>
                                <span
                                    class="text-lg font-bold text-dealytics-pink"
                                    >{{ hotDeals }}</span
                                >
                                <span class="ml-1 text-xs text-muted-foreground"
                                    >Promos chaudes</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <TrendingDown
                                class="size-4 text-dealytics-cyan"
                            />
                            <div>
                                <span
                                    class="text-lg font-bold text-dealytics-cyan"
                                    >{{ totalSavings }}€</span
                                >
                                <span class="ml-1 text-xs text-muted-foreground"
                                    >Economies totales</span
                                >
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div ref="searchContainer" class="relative mb-4">
            <div
                class="border-gradient flex items-center gap-3 rounded-xl px-4 py-3"
            >
                <Search class="size-5 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Rechercher des jeux... (ex: Cyberpunk, Elden Ring)"
                    class="flex-1 bg-transparent text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                    role="combobox"
                    :aria-expanded="showSuggestions"
                    aria-autocomplete="list"
                    autocomplete="off"
                    @input="onSearchInput"
                    @focus="onSearchFocus"
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter.prevent="onSearchEnter"
                    @keydown.esc="closeSuggestions"
                />
            </div>

            <!-- Suggestions dropdown -->
            <div
                v-if="showSuggestions && suggestions.length > 0"
                class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border border-border/60 bg-card/95 shadow-2xl backdrop-blur-xl"
            >
                <button
                    v-for="(game, index) in suggestions"
                    :key="game.id"
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left transition-colors"
                    :class="index === activeSuggestion ? 'bg-dealytics-purple/15' : 'hover:bg-secondary/60'"
                    @mouseenter="activeSuggestion = index"
                    @click="selectSuggestion(game)"
                >
                    <GameImage
                        :src="game.image"
                        :alt="game.title"
                        class="h-9 w-16 shrink-0 rounded-md object-cover"
                    />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                        {{ game.title }}
                    </span>
                    <span class="shrink-0 text-sm font-bold text-dealytics-cyan">
                        {{ game.price !== null ? `${game.price.toFixed(2)}€` : '' }}
                    </span>
                    <span
                        v-if="game.discount > 0"
                        class="shrink-0 rounded bg-dealytics-purple px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        -{{ Math.round(game.discount) }}%
                    </span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="border-gradient mb-8 rounded-xl px-4 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <div
                    class="flex items-center gap-2 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    <SlidersHorizontal class="size-3.5" />
                    Filtres
                </div>

                <Select v-model="selectedPlatform">
                    <SelectTrigger
                        class="h-8 w-35 rounded-lg border-border/50 bg-secondary text-xs"
                    >
                        <SelectValue placeholder="Plateforme" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Plateformes</SelectItem>
                        <SelectItem
                            v-for="platform in PLATFORMS"
                            :key="platform.slug"
                            :value="platform.slug"
                        >
                            {{ platform.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="maxPrice">
                    <SelectTrigger
                        class="h-8 w-32.5 rounded-lg border-border/50 bg-secondary text-xs"
                    >
                        <SelectValue placeholder="Prix max" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tous les prix</SelectItem>
                        <SelectItem value="5">Moins de 5€</SelectItem>
                        <SelectItem value="10">Moins de 10€</SelectItem>
                        <SelectItem value="15">Moins de 15€</SelectItem>
                        <SelectItem value="20">Moins de 20€</SelectItem>
                        <SelectItem value="30">Moins de 30€</SelectItem>
                        <SelectItem value="50">Moins de 50€</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="sortBy">
                    <SelectTrigger
                        class="h-8 w-35 rounded-lg border-border/50 bg-secondary text-xs"
                    >
                        <SelectValue placeholder="Trier par" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="popular">Popularité</SelectItem>
                        <SelectItem value="price">Prix</SelectItem>
                        <SelectItem value="savings">Réduction</SelectItem>
                        <SelectItem value="title">Titre</SelectItem>
                    </SelectContent>
                </Select>

                <button
                    type="button"
                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        onSaleOnly
                            ? 'border-dealytics-pink/60 bg-dealytics-pink/15 text-dealytics-pink'
                            : 'border-border/50 bg-secondary text-muted-foreground hover:border-dealytics-pink/40 hover:text-dealytics-pink/80'
                    "
                    @click="onSaleOnly = !onSaleOnly"
                >
                    <Flame
                        class="size-3 transition-colors duration-150"
                        :class="onSaleOnly ? 'text-dealytics-pink' : 'text-muted-foreground'"
                    />
                    En promo
                </button>
            </div>
        </div>

        <!-- Results -->
        <div
            v-if="loading"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <GameCardSkeleton v-for="i in 8" :key="i" />
        </div>

        <div
            v-else-if="displayedGames.length > 0"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <GameCard
                v-for="(game, index) in displayedGames"
                :key="game.id"
                :game="game"
                class="animate-card-in"
                :style="{ animationDelay: `${Math.min(index, 11) * 50}ms` }"
            />
        </div>

        <!-- Error state -->
        <div
            v-else-if="loadError"
            class="flex flex-col items-center justify-center py-20 text-center"
        >
            <PlugZap class="mb-4 size-16 text-muted-foreground/30" />
            <h3 class="font-heading text-lg font-semibold text-foreground">
                Impossible de charger les jeux
            </h3>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Le service de prix est momentanément indisponible. Vérifie ta
                connexion ou réessaie dans un instant.
            </p>
            <Button
                variant="outline"
                class="mt-4 gap-2 rounded-xl border-dealytics-purple/50 text-dealytics-purple hover:bg-dealytics-purple/10"
                @click="loadGames"
            >
                <RefreshCw class="size-4" />
                Réessayer
            </Button>
        </div>

        <!-- Load More -->
        <div v-if="hasMore && !loading && displayedGames.length > 0" class="mt-8 flex justify-center">
            <Button
                variant="outline"
                class="gap-2 rounded-xl border-dealytics-purple/50 px-8 text-dealytics-purple hover:bg-dealytics-purple/10"
                :disabled="loadingMore"
                @click="loadMore"
            >
                <template v-if="loadingMore">
                    <div class="size-4 animate-spin rounded-full border-2 border-dealytics-purple border-t-transparent" />
                    Chargement...
                </template>
                <template v-else>
                    Charger plus de résultats
                </template>
            </Button>
        </div>

        <!-- Loading more skeletons -->
        <div
            v-if="loadingMore"
            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <GameCardSkeleton v-for="i in 4" :key="'more-' + i" />
        </div>

        <div
            v-else-if="hasSearched && !loading && !loadError && displayedGames.length === 0"
            class="flex flex-col items-center justify-center py-20 text-center"
        >
            <Gamepad2 class="mb-4 size-16 text-muted-foreground/30" />
            <h3 class="font-heading text-lg font-semibold text-foreground">
                Aucun résultat
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Essayez avec un autre terme de recherche ou modifiez vos
                filtres.
            </p>
        </div>

        <!-- Separator -->
        <div class="mt-16 h-px bg-linear-to-r from-transparent via-border to-transparent" />

        <!-- Why Dealytics -->
        <section class="mt-16">
            <div v-reveal class="text-center">
                <h2 class="font-heading text-2xl font-bold text-foreground md:text-3xl">
                    Pourquoi choisir Dealytics ?
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Tout ce dont vous avez besoin pour économiser sur les jeux
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    v-reveal="{ delay: index * 90 }"
                    class="group rounded-2xl border border-border/50 bg-secondary/30 p-6 text-center transition-all duration-300 hover:border-dealytics-purple/40 hover:bg-secondary/50"
                >
                    <div
                        class="mx-auto flex size-12 items-center justify-center rounded-xl bg-dealytics-purple/15 transition-transform duration-300 group-hover:scale-110"
                    >
                        <component :is="feature.icon" class="size-5 text-dealytics-purple" />
                    </div>
                    <h3 class="mt-4 font-heading text-base font-semibold text-foreground">
                        {{ feature.title }}
                    </h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                        {{ feature.description }}
                    </p>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section
            v-reveal
            class="border-gradient-strong relative mt-12 overflow-hidden rounded-2xl p-8 text-center md:p-12"
        >
            <div
                class="bg-glow-purple pointer-events-none absolute -top-24 left-1/2 size-96 -translate-x-1/2 opacity-30"
            />
            <div class="relative">
                <h2 class="font-heading text-2xl font-bold text-foreground md:text-3xl">
                    Commencez dès aujourd'hui à économiser
                </h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    Rejoignez les milliers de gamers qui ne paient jamais le prix fort
                </p>
                <Button
                    as-child
                    class="mt-6 gap-2 rounded-xl bg-dealytics-purple px-6 text-white hover:bg-dealytics-deep-purple"
                >
                    <Link href="/register">
                        Commencer gratuitement
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
