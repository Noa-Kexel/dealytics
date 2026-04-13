<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Search,
    SlidersHorizontal,
    Flame,
    TrendingDown,
    Zap,
    Gamepad2,

} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import GameCard from '@/components/GameCard.vue';
import GameCardSkeleton from '@/components/GameCardSkeleton.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Deal {
    dealID: string;
    title: string;
    salePrice: string;
    normalPrice: string;
    savings: string;
    metacriticScore: string;
    steamRatingPercent: string;
    thumb: string;
    dealRating: string;
    storeID: string;
    isOnSale: string;
    gameID: string;
}

interface StoreInfo {
    storeID: string;
    storeName: string;
}

const searchQuery = ref('');
const deals = ref<Deal[]>([]);
const loading = ref(false);
const hasSearched = ref(false);
const selectedStore = ref<string>('all');
const maxPrice = ref<string>('all');
const sortBy = ref<string>('Deal Rating');
const onSaleOnly = ref(true);

const stores = ref<StoreInfo[]>([]);

const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);

// Stats
const gamesTracked = ref(0);
const hotDeals = ref(0);
const totalSavings = ref(0);

// Fetch stores on mount
async function fetchStores() {
    try {
        const response = await fetch(
            'https://www.cheapshark.com/api/1.0/stores',
        );
        const data = await response.json();
        stores.value = data
            .filter((s: { isActive: number }) => s.isActive === 1)
            .map((s: { storeID: string; storeName: string }) => ({
                storeID: s.storeID,
                storeName: s.storeName,
            }));
    } catch {
        // silently fail
    }
}

fetchStores();

// Search deals
async function searchDeals() {
    if (!searchQuery.value.trim()) {
        deals.value = [];
        hasSearched.value = false;

        return;
    }

    loading.value = true;
    hasSearched.value = true;

    try {
        const params = new URLSearchParams({
            title: searchQuery.value,
            pageSize: '30',
            sortBy: sortBy.value,
        });

        if (onSaleOnly.value) {
            params.set('onSale', '1');
        }

        if (selectedStore.value !== 'all') {
            params.set('storeID', selectedStore.value);
        }

        if (maxPrice.value !== 'all') {
            params.set('upperPrice', maxPrice.value);
        }

        const response = await fetch(
            `https://www.cheapshark.com/api/1.0/deals?${params.toString()}`,
        );
        const data: Deal[] = await response.json();
        deals.value = data;

        // Update stats
        gamesTracked.value = data.length;
        hotDeals.value = data.filter((d) => parseFloat(d.savings) > 50).length;
        totalSavings.value = Math.round(
            data.reduce(
                (acc, d) =>
                    acc + (parseFloat(d.normalPrice) - parseFloat(d.salePrice)),
                0,
            ),
        );
    } catch {
        deals.value = [];
    } finally {
        loading.value = false;
    }
}

// Debounced search
function onSearchInput() {
    if (debounceTimer.value) {
clearTimeout(debounceTimer.value);
}

    debounceTimer.value = setTimeout(() => {
        searchDeals();
    }, 400);
}

// Re-search when filters change
watch([selectedStore, maxPrice, sortBy, onSaleOnly], () => {
    if (hasSearched.value) {
        searchDeals();
    }
});

// Load top deals on mount
async function loadTopDeals() {
    loading.value = true;
    hasSearched.value = true;

    try {
        const response = await fetch(
            'https://www.cheapshark.com/api/1.0/deals?sortBy=Deal Rating&pageSize=12&onSale=1',
        );
        const data: Deal[] = await response.json();
        deals.value = data;

        gamesTracked.value = data.length;
        hotDeals.value = data.filter((d) => parseFloat(d.savings) > 50).length;
        totalSavings.value = Math.round(
            data.reduce(
                (acc, d) =>
                    acc + (parseFloat(d.normalPrice) - parseFloat(d.salePrice)),
                0,
            ),
        );
    } catch {
        deals.value = [];
    } finally {
        loading.value = false;
    }
}

loadTopDeals();
</script>

<template>
    <Head title="Recherche" />

    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
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

            <div class="relative">
                <!-- Badge -->
                <div
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-dealytics-cyan/30 bg-dealytics-cyan/10 px-3 py-1"
                >
                    <span
                        class="size-2 animate-pulse rounded-full bg-dealytics-cyan"
                    />
                    <span
                        class="text-xs font-medium tracking-wider text-dealytics-cyan uppercase"
                    >
                        Suivi des prix en direct
                    </span>
                </div>

                <!-- Title -->
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
                    Comparez les prix sur toutes les plateformes. Soyez alerté
                    quand vos jeux préférés atteignent leur prix le plus bas.
                </p>

                <!-- Stats -->
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
                        <TrendingDown class="size-4 text-dealytics-cyan" />
                        <div>
                            <span class="text-lg font-bold text-dealytics-cyan"
                                >${{ totalSavings }}</span
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
        <div class="relative mb-4">
            <div
                class="border-gradient flex items-center gap-3 rounded-xl px-4 py-3"
            >
                <Search class="size-5 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Rechercher des jeux... (ex: Cyberpunk, Elden Ring)"
                    class="flex-1 bg-transparent text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                    @input="onSearchInput"
                />
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

                <Select v-model="selectedStore">
                    <SelectTrigger
                        class="h-8 w-35 rounded-lg border-border/50 bg-secondary text-xs"
                    >
                        <SelectValue placeholder="Plateforme" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Toutes</SelectItem>
                        <SelectItem
                            v-for="store in stores"
                            :key="store.storeID"
                            :value="store.storeID"
                        >
                            {{ store.storeName }}
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
                        <SelectItem value="5">Moins de 5$</SelectItem>
                        <SelectItem value="10">Moins de 10$</SelectItem>
                        <SelectItem value="15">Moins de 15$</SelectItem>
                        <SelectItem value="20">Moins de 20$</SelectItem>
                        <SelectItem value="30">Moins de 30$</SelectItem>
                        <SelectItem value="50">Moins de 50$</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="sortBy">
                    <SelectTrigger
                        class="h-8 w-35 rounded-lg border-border/50 bg-secondary text-xs"
                    >
                        <SelectValue placeholder="Trier par" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="Deal Rating">Score Deal</SelectItem>
                        <SelectItem value="Price">Prix</SelectItem>
                        <SelectItem value="Savings">Réduction</SelectItem>
                        <SelectItem value="Title">Titre</SelectItem>
                        <SelectItem value="Metacritic">Metacritic</SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 rounded-lg text-xs"
                    :class="
                        onSaleOnly
                            ? 'border-dealytics-cyan/50 text-dealytics-cyan'
                            : 'text-muted-foreground'
                    "
                    @click="onSaleOnly = !onSaleOnly"
                >
                    <Flame class="size-3" />
                    En promo
                </Button>
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
            v-else-if="deals.length > 0"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <GameCard v-for="deal in deals" :key="deal.dealID" :deal="deal" />
        </div>

        <div
            v-else-if="hasSearched"
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
    </div>
</template>
