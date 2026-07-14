<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Heart, Bell, TrendingDown, Star, ArrowUpDown, Flame, Trash2, Gamepad2, ExternalLink, Link2 } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import GameImage from '@/components/GameImage.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { vReveal } from '@/directives/reveal';
import { api } from '@/lib/api';

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

const avgDiscount = computed(() => {
    const discounted = enrichedFavorites.value.filter((f) => f.savings !== undefined && f.savings > 0);

    if (discounted.length === 0) {
        return 0;
    }

    const sum = discounted.reduce((acc, f) => acc + (f.savings || 0), 0);

    return Math.round(sum / discounted.length);
});

// ── Steam wishlist ──────────────────────────────────────────
interface WishlistItem {
    appId: number;
    title: string;
    image: string | null;
    isFree: boolean;
    price: number | null;
    normalPrice: number | null;
    discount: number;
    steamPrice: number | null;
    source: 'nexarda' | 'steam';
    nexardaId: string | null;
    storeUrl: string;
}

interface WishlistResponse {
    connected: boolean;
    available?: boolean;
    items: WishlistItem[];
}

const steamConnected = ref(false);
const steamInput = ref('');
const wishlist = ref<WishlistItem[]>([]);
const wishlistAvailable = ref(true);
const wishlistLoading = ref(false);
const connecting = ref(false);
const steamError = ref('');

async function loadWishlist() {
    wishlistLoading.value = true;

    try {
        const data = await api<WishlistResponse>('/api/steam/wishlist');
        steamConnected.value = data.connected;
        wishlistAvailable.value = data.available ?? true;
        wishlist.value = data.items ?? [];
    } catch {
        // ignore — section just stays disconnected
    } finally {
        wishlistLoading.value = false;
    }
}

async function connectSteam() {
    if (!steamInput.value.trim()) {
        return;
    }

    connecting.value = true;
    steamError.value = '';

    try {
        const data = await api<WishlistResponse>('/api/steam/wishlist', {
            method: 'POST',
            body: { steam_input: steamInput.value.trim() },
        });
        steamConnected.value = true;
        wishlistAvailable.value = data.available ?? true;
        wishlist.value = data.items ?? [];
        steamInput.value = '';
    } catch {
        steamError.value = "Profil introuvable. Vérifie l'URL / le SteamID, et que ton profil et ta wishlist sont publics.";
    } finally {
        connecting.value = false;
    }
}

async function disconnectSteam() {
    await api('/api/steam/wishlist', { method: 'DELETE' });
    steamConnected.value = false;
    wishlist.value = [];
    wishlistAvailable.value = true;
}

// Open our own detail page (multi-store prices). The Nexarda id is usually
// resolved server-side; otherwise resolve by title, then fall back to Steam.
async function openWishlistGame(item: WishlistItem) {
    if (item.nexardaId) {
        router.visit(`/game/${item.nexardaId}`);

        return;
    }

    try {
        const data = await api<{ games: { id: string }[] }>(
            `/api/games?q=${encodeURIComponent(item.title)}`,
        );
        const first = data.games?.[0];

        if (first) {
            router.visit(`/game/${first.id}`);

            return;
        }
    } catch {
        // fall through to Steam store
    }

    window.open(item.storeUrl, '_blank', 'noopener');
}

onMounted(async () => {
    loadWishlist();

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
            const response = await fetch(`/api/nexarda/game/${fav.game_id}`);

            if (response.ok) {
                const data = await response.json();

                if (data?.lowest != null) {
                    const discount = Math.round(data.maxDiscount || 0);
                    fav.currentPrice = data.lowest;
                    fav.normalPrice = discount > 0 && discount < 100
                        ? data.lowest / (1 - discount / 100)
                        : (data.highest ?? data.lowest);
                    fav.savings = discount;
                    fav.dealRating = 0;
                }
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
        <div v-reveal="{ y: 16 }" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
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
                <div class="text-2xl font-bold text-dealytics-cyan">{{ totalSaved }}€</div>
                <div class="text-xs text-muted-foreground">Economies potentielles</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Star class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-foreground">{{ avgDiscount > 0 ? `${avgDiscount}%` : '--' }}</div>
                <div class="text-xs text-muted-foreground">Réduction moyenne</div>
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
                    <GameImage
                        :src="fav.thumb"
                        :alt="fav.title"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                    />

                    <!-- Deal badge (based on discount) -->
                    <div v-if="fav.savings && fav.savings >= 50" class="absolute top-2 left-2">
                        <span class="inline-flex items-center gap-1 rounded-md border border-dealytics-pink/30 bg-dealytics-pink/20 px-2 py-0.5 text-[10px] font-bold uppercase text-dealytics-pink">
                            <Flame class="size-2.5" />
                            HOT
                        </span>
                    </div>
                    <div v-else-if="fav.savings && fav.savings >= 25" class="absolute top-2 left-2">
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
                                {{ fav.currentPrice === 0 ? 'Gratuit' : `${fav.currentPrice.toFixed(2)}€` }}
                            </span>
                            <span
                                v-if="fav.normalPrice && fav.savings && fav.savings > 0"
                                class="text-xs text-muted-foreground line-through"
                            >
                                {{ fav.normalPrice.toFixed(2) }}€
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

        <!-- Steam Wishlist -->
        <section v-reveal class="mt-12">
            <div class="mb-5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-cyan/15">
                        <Gamepad2 class="size-5 text-dealytics-cyan" />
                    </div>
                    <div>
                        <h2 class="font-heading text-xl font-bold text-foreground">Ma wishlist Steam</h2>
                        <p class="text-sm text-muted-foreground">Vos jeux désirés sur Steam, avec leur prix actuel</p>
                    </div>
                </div>
                <button
                    v-if="steamConnected"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-all duration-200 hover:border-red-500/60 hover:bg-red-500/20 hover:text-red-300 active:scale-95"
                    @click="disconnectSteam"
                >
                    <Trash2 class="size-3.5" />
                    Déconnecter
                </button>
            </div>

            <!-- Connect form -->
            <div v-if="!steamConnected" class="border-gradient rounded-xl p-6">
                <p class="mb-3 text-sm text-muted-foreground">
                    Connecte ton profil Steam pour afficher ta liste de souhaits.
                    Colle l'URL de ton profil ou ton SteamID (profil et wishlist publics).
                </p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        v-model="steamInput"
                        type="text"
                        placeholder="https://steamcommunity.com/id/ton-pseudo/"
                        class="h-10 flex-1 text-sm"
                        @keyup.enter="connectSteam"
                    />
                    <Button
                        class="h-10 gap-2 bg-dealytics-cyan text-dealytics-dark hover:bg-dealytics-cyan/90"
                        :disabled="connecting || !steamInput.trim()"
                        @click="connectSteam"
                    >
                        <div v-if="connecting" class="size-4 animate-spin rounded-full border-2 border-dealytics-dark border-t-transparent" />
                        <Link2 v-else class="size-4" />
                        Connecter
                    </Button>
                </div>
                <p v-if="steamError" class="mt-2 text-xs text-red-400">{{ steamError }}</p>
            </div>

            <!-- Loading -->
            <div v-else-if="wishlistLoading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-xl bg-secondary/40" />
            </div>

            <!-- Private / empty -->
            <div
                v-else-if="!wishlistAvailable"
                class="border-gradient rounded-xl p-6 text-center"
            >
                <Gamepad2 class="mx-auto mb-2 size-8 text-muted-foreground/40" />
                <p class="text-sm text-muted-foreground">
                    Impossible de lire cette wishlist. Vérifie qu'elle est bien
                    <span class="font-medium text-foreground">publique</span> dans tes préférences Steam.
                </p>
            </div>
            <div
                v-else-if="wishlist.length === 0"
                class="border-gradient rounded-xl p-6 text-center"
            >
                <Gamepad2 class="mx-auto mb-2 size-8 text-muted-foreground/40" />
                <p class="text-sm text-muted-foreground">Ta wishlist Steam est vide.</p>
            </div>

            <!-- Wishlist grid -->
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="item in wishlist"
                    :key="item.appId"
                    class="group border-gradient flex cursor-pointer items-center gap-3 overflow-hidden rounded-xl p-2 transition-all duration-300 hover:scale-[1.02]"
                    @click="openWishlistGame(item)"
                >
                    <GameImage
                        :src="item.image"
                        :alt="item.title"
                        class="h-16 w-28 shrink-0 rounded-lg object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-semibold text-foreground">{{ item.title }}</h3>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-sm font-bold text-dealytics-cyan">
                                <template v-if="item.isFree">Gratuit</template>
                                <template v-else-if="item.price === null">À venir</template>
                                <template v-else>{{ item.price.toFixed(2) }}€</template>
                            </span>
                            <span
                                v-if="item.discount > 0 && item.normalPrice"
                                class="text-[11px] text-muted-foreground line-through"
                            >
                                {{ item.normalPrice.toFixed(2) }}€
                            </span>
                            <span
                                v-if="item.discount > 0"
                                class="rounded bg-dealytics-purple px-1.5 py-0.5 text-[10px] font-bold text-white"
                            >
                                -{{ item.discount }}%
                            </span>
                        </div>
                        <p
                            v-if="item.price !== null && !item.isFree"
                            class="mt-0.5 text-[10px] text-muted-foreground/70"
                        >
                            {{ item.source === 'nexarda' ? 'Meilleur prix multi-magasins' : 'Prix Steam' }}
                        </p>
                    </div>
                    <ExternalLink class="size-4 shrink-0 text-muted-foreground/50 transition-colors group-hover:text-dealytics-cyan" />
                </div>
            </div>
        </section>
    </div>
</template>
