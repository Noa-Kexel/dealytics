<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Heart, Bell, TrendingDown, Star, ArrowUpDown } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface FavoriteGame {
    gameID: string;
    title: string;
    thumb: string;
    addedAt: string;
}

const favorites = ref<FavoriteGame[]>([]);
const sortBy = ref('date');

onMounted(() => {
    loadFavorites();
});

function loadFavorites() {
    try {
        favorites.value = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');
    } catch {
        favorites.value = [];
    }
}

function removeFavorite(gameID: string) {
    favorites.value = favorites.value.filter((f) => f.gameID !== gameID);
    localStorage.setItem('dealytics_favorites', JSON.stringify(favorites.value));
}

const sortedFavorites = computed(() => {
    const sorted = [...favorites.value];

    if (sortBy.value === 'date') {
        sorted.sort((a, b) => new Date(b.addedAt).getTime() - new Date(a.addedAt).getTime());
    } else if (sortBy.value === 'title') {
        sorted.sort((a, b) => a.title.localeCompare(b.title));
    }

    return sorted;
});
</script>

<template>
    <Head title="Favoris" />

    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
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
                <div class="text-2xl font-bold text-dealytics-purple">{{ favorites.length }}</div>
                <div class="text-xs text-muted-foreground">Jeux suivis</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <Bell class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-foreground">0</div>
                <div class="text-xs text-muted-foreground">Alertes actives</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-dealytics-cyan">$0</div>
                <div class="text-xs text-muted-foreground">Total économisé</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Star class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-foreground">--</div>
                <div class="text-xs text-muted-foreground">Score moyen</div>
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
                    <SelectTrigger class="h-8 w-35 rounded-lg border-border/50 bg-secondary text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="date">Date d'ajout</SelectItem>
                        <SelectItem value="title">Titre</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Favorites Grid -->
        <div v-if="sortedFavorites.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="fav in sortedFavorites"
                :key="fav.gameID"
                class="group border-gradient cursor-pointer overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.02]"
            >
                <div class="relative aspect-16/10 overflow-hidden bg-secondary">
                    <img
                        :src="fav.thumb"
                        :alt="fav.title"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                        loading="lazy"
                    />
                    <button
                        class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-black/50 backdrop-blur-sm transition-all hover:bg-red-500/50"
                        @click.stop="removeFavorite(fav.gameID)"
                    >
                        <Heart class="size-3.5 fill-dealytics-pink text-dealytics-pink" />
                    </button>
                </div>
                <div class="p-3">
                    <h3 class="truncate text-sm font-semibold text-foreground">{{ fav.title }}</h3>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Ajouté le {{ new Date(fav.addedAt).toLocaleDateString('fr-FR') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <Heart class="mb-4 size-16 text-muted-foreground/20" />
            <h3 class="font-heading text-lg font-semibold text-foreground">Aucun favori</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Ajoutez des jeux à vos favoris depuis la page de recherche.
            </p>
        </div>
    </div>
</template>
