<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Heart, Bell, TrendingDown, Star, ArrowUpDown, Flame, Trash2 } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAlerts } from '@/composables/useAlerts';
import { useFavorites } from '@/composables/useFavorites';
import type { FavoriteGame } from '@/composables/useFavorites';

interface EnrichedFavorite extends FavoriteGame {
    currentPrice?: number;
    normalPrice?: number;
    savings?: number;
    dealRating?: number;
    loading?: boolean;
}

const { getActiveAlerts } = useAlerts();
const { favorites: rawFavorites, loadFavorites, removeFavorite: removeFav } = useFavorites();

const enrichedFavorites = ref<EnrichedFavorite[]>([]);
const sortBy = ref('date');

// Reactive computed stats — update automatically when enrichedFavorites change
const totalSaved = computed(() => {
    return Math.round(
        enrichedFavorites.value.reduce((acc, f) => {
            if (f.normalPrice !== undefined && f.currentPrice !== undefined) {
                return acc + (f.normalPrice - f.currentPrice);
            }

            return acc;
        }, 0),
    );
});

const avgScore = computed(() => {
    const scored = enrichedFavorites.value.filter((f) => f.dealRating !== undefined && f.dealRating > 0);

    if (scored.length === 0) {
        return 0;
    }

    const sum = scored.reduce((acc, f) => acc + (f.dealRating || 0), 0);

    return Math.round((sum / scored.length) * 10);
});

onMounted(async () => {
    await loadFavorites();
    enrichedFavorites.value = rawFavorites.value.map((f) => ({
        ...f,
        loading: true,
    }));
    await fetchPrices();
});

async function fetchPrices() {
    for (const fav of enrichedFavorites.value) {
        try {
            const response = await fetch(
                `https://www.cheapshark.com/api/1.0/games?id=${fav.game_id}`,
            );
            const data = await response.json();

            if (data?.deals?.length) {
                const best = data.deals.reduce(
                    (b: { price: string }, d: { price: string }) =>
                        parseFloat(d.price) < parseFloat(b.price) ? d : b,
                );
                fav.currentPrice = parseFloat(best.price);
                fav.normalPrice = parseFloat(best.retailPrice);
                fav.savings = parseFloat(best.savings);
                fav.dealRating = parseFloat(best.dealRating || '0');
            }
        } catch {
            // skip
        } finally {
            fav.loading = false;
        }
    }
}

async function removeFavorite(gameId: string) {
    await removeFav(gameId);
    enrichedFavorites.value = enrichedFavorites.value.filter((f) => f.game_id !== gameId);
}

const sortedFavorites = computed(() => {
    const sorted = [...enrichedFavorites.value];

    if (sortBy.value === 'date') {
        sorted.sort((a, b) => new Date(b.created_at || '').getTime() - new Date(a.created_at || '').getTime());
    } else if (sortBy.value === 'title') {
        sorted.sort((a, b) => a.title.localeCompare(b.title));
    } else if (sortBy.value === 'price') {
        sorted.sort((a, b) => (a.currentPrice ?? 999) - (b.currentPrice ?? 999));
    } else if (sortBy.value === 'discount') {
        sorted.sort((a, b) => (b.savings ?? 0) - (a.savings ?? 0));
    }

    return sorted;
});
</script>

<template>
    <Head title="Favoris" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Header -->
        <div class="mb-8 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-pink/20">
                <Heart class="size-5 fill-dealytics-pink text-dealytics-pink" />
            </div>
            <div>
                <h1 class="font-heading text-3xl font-bold text-foreground">Vos Favoris</h1>
                <p class="text-sm text-muted-foreground">Suivez les offres sur vos jeux préférés</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-pink/20">
                    <Heart class="size-4 text-dealytics-pink" />
                </div>
                <div class="text-2xl font-bold text-dealytics-purple">{{ enrichedFavorites.length }}</div>
                <div class="text-xs text-muted-foreground">Jeux suivis</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <Bell class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-foreground">{{ getActiveAlerts().length }}</div>
                <div class="text-xs text-muted-foreground">Alertes actives</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-dealytics-cyan">${{ totalSaved }}</div>
                <div class="text-xs text-muted-foreground">Economies potentielles</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Star class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-foreground">{{ avgScore > 0 ? avgScore : '--' }}</div>
                <div class="text-xs text-muted-foreground">Score deal moyen</div>
            </div>
        </div>

        <!-- Sort -->
        <div class="mb-6 border-gradient rounded-xl px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    <ArrowUpDown class="size-3.5" />
                    Trier
                </div>
                <Select v-model="sortBy">
                    <SelectTrigger class="h-8 w-[140px] rounded-lg border-border/50 bg-secondary text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="date">Date d'ajout</SelectItem>
                        <SelectItem value="title">Titre</SelectItem>
                        <SelectItem value="price">Prix</SelectItem>
                        <SelectItem value="discount">Réduction</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Favorites Grid -->
        <div v-if="sortedFavorites.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="fav in sortedFavorites"
                :key="fav.game_id"
                :href="`/game/${fav.game_id}`"
                class="group border-gradient overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.02]"
            >
                <div class="relative aspect-[16/10] overflow-hidden bg-secondary">
                    <img
                        :src="fav.thumb"
                        :alt="fav.title"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                        loading="lazy"
                    />

                    <!-- Deal badge -->
                    <div v-if="fav.dealRating !== undefined && fav.dealRating >= 8" class="absolute top-2 left-2">
                        <span class="inline-flex items-center gap-1 rounded-md border border-dealytics-pink/30 bg-dealytics-pink/20 px-2 py-0.5 text-[10px] font-bold uppercase text-dealytics-pink">
                            <Flame class="size-2.5" />
                            HOT
                        </span>
                    </div>
                    <div v-else-if="fav.dealRating !== undefined && fav.dealRating >= 5" class="absolute top-2 left-2">
                        <span class="inline-flex items-center gap-1 rounded-md border border-dealytics-cyan/30 bg-dealytics-cyan/20 px-2 py-0.5 text-[10px] font-bold uppercase text-dealytics-cyan">
                            <Star class="size-2.5" />
                            BON
                        </span>
                    </div>

                    <!-- Discount badge -->
                    <div
                        v-if="fav.savings && fav.savings > 0"
                        class="absolute top-2 right-2 rounded-md bg-dealytics-purple px-2 py-0.5 text-[11px] font-bold text-white"
                    >
                        -{{ Math.round(fav.savings) }}%
                    </div>

                    <!-- Remove button -->
                    <button
                        class="absolute bottom-2 right-2 flex size-7 items-center justify-center rounded-full bg-black/50 backdrop-blur-sm transition-all hover:bg-red-500/50"
                        @click.prevent="removeFavorite(fav.game_id)"
                    >
                        <Trash2 class="size-3.5 text-white/70" />
                    </button>
                </div>

                <div class="p-3">
                    <h3 class="truncate text-sm font-semibold text-foreground">{{ fav.title }}</h3>

                    <div class="mt-2 flex items-end justify-between">
                        <div v-if="fav.loading" class="h-5 w-20 animate-pulse rounded bg-secondary" />
                        <div v-else-if="fav.currentPrice !== undefined" class="flex items-baseline gap-2">
                            <span class="text-lg font-bold text-dealytics-cyan">
                                {{ fav.currentPrice === 0 ? 'Gratuit' : `$${fav.currentPrice.toFixed(2)}` }}
                            </span>
                            <span
                                v-if="fav.normalPrice && fav.savings && fav.savings > 0"
                                class="text-xs text-muted-foreground line-through"
                            >
                                ${{ fav.normalPrice.toFixed(2) }}
                            </span>
                        </div>
                        <div v-else class="text-xs text-muted-foreground">Prix indisponible</div>

                        <span class="text-[10px] text-muted-foreground">
                            {{ new Date(fav.created_at || '').toLocaleDateString('fr-FR') }}
                        </span>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <Heart class="mb-4 size-16 text-muted-foreground/20" />
            <h3 class="font-heading text-lg font-semibold text-foreground">Aucun favori</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Ajoutez des jeux à vos favoris depuis la page de recherche.
            </p>
            <Link href="/" class="mt-4 text-sm text-dealytics-purple hover:underline">
                Rechercher des jeux
            </Link>
        </div>
    </div>
</template>
