<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Flame, Star, Heart } from 'lucide-vue-next';
import { computed } from 'vue';

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

const props = defineProps<{
    deal: Deal;
}>();

const savingsPercent = computed(() => Math.round(parseFloat(props.deal.savings)));

const dealBadge = computed(() => {
    const rating = parseFloat(props.deal.dealRating);

    if (rating >= 8) {
        return { label: 'HOT', class: 'bg-dealytics-pink/20 text-dealytics-pink border-dealytics-pink/30' };
    }

    if (rating >= 5) {
        return { label: 'BON', class: 'bg-dealytics-cyan/20 text-dealytics-cyan border-dealytics-cyan/30' };
}

    return null;
});

const salePrice = computed(() => parseFloat(props.deal.salePrice));
const normalPrice = computed(() => parseFloat(props.deal.normalPrice));

// Store logos mapping (CheapShark store IDs)
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

const storeName = computed(() => storeNames[props.deal.storeID] || 'Store');

function openDeal() {
    router.visit(`/game/${props.deal.gameID}`);
}

// Favorite logic (localStorage)
const isFavorite = computed(() => {
    try {
        const favs = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');

        return favs.some((f: { gameID: string }) => f.gameID === props.deal.gameID);
    } catch {
        return false;
    }
});

function toggleFavorite(e: Event) {
    e.stopPropagation();

    try {
        const favs = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');
        const idx = favs.findIndex((f: { gameID: string }) => f.gameID === props.deal.gameID);

        if (idx >= 0) {
            favs.splice(idx, 1);
        } else {
            favs.push({
                gameID: props.deal.gameID,
                title: props.deal.title,
                thumb: props.deal.thumb,
                addedAt: new Date().toISOString(),
            });
        }

        localStorage.setItem('dealytics_favorites', JSON.stringify(favs));
    } catch {
        // silently fail
    }
}
</script>

<template>
    <div
        class="group border-gradient cursor-pointer overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.02] hover:border-gradient-strong"
        @click="openDeal"
    >
        <!-- Image -->
        <div class="relative aspect-16/10 overflow-hidden bg-secondary">
            <img
                :src="deal.thumb"
                :alt="deal.title"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                loading="lazy"
                @error="($event.target as HTMLImageElement).src = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 320 180%22><rect fill=%22%23151025%22 width=%22320%22 height=%22180%22/><text x=%2250%%22 y=%2250%%22 fill=%22%23555%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22>No Image</text></svg>'"
            />

            <!-- Badges overlay -->
            <div class="absolute top-2 left-2 flex gap-1.5">
                <span
                    v-if="dealBadge"
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                    :class="dealBadge.class"
                >
                    <Flame v-if="dealBadge.label === 'HOT'" class="size-2.5" />
                    <Star v-else class="size-2.5" />
                    {{ dealBadge.label }}
                </span>
            </div>

            <!-- Discount badge -->
            <div
                v-if="savingsPercent > 0"
                class="absolute top-2 right-2 rounded-md bg-dealytics-purple px-2 py-0.5 text-[11px] font-bold text-white"
            >
                -{{ savingsPercent }}%
            </div>

            <!-- Favorite button -->
            <button
                class="absolute bottom-2 right-2 flex size-7 items-center justify-center rounded-full bg-black/50 backdrop-blur-sm transition-all hover:bg-black/70"
                @click="toggleFavorite"
            >
                <Heart
                    class="size-3.5 transition-colors"
                    :class="isFavorite ? 'fill-dealytics-pink text-dealytics-pink' : 'text-white/70'"
                />
            </button>
        </div>

        <!-- Content -->
        <div class="p-3">
            <h3 class="truncate text-sm font-semibold text-foreground">
                {{ deal.title }}
            </h3>

            <div class="mt-1 flex items-center gap-1.5">
                <span class="text-[10px] text-muted-foreground">{{ storeName }}</span>
                <span
                    v-if="deal.metacriticScore && deal.metacriticScore !== '0'"
                    class="rounded bg-dealytics-cyan/10 px-1.5 py-0.5 text-[10px] font-medium text-dealytics-cyan"
                >
                    {{ deal.metacriticScore }}/100
                </span>
            </div>

            <div class="mt-2 flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-bold text-dealytics-cyan">
                        {{ salePrice === 0 ? 'Gratuit' : `$${salePrice.toFixed(2)}` }}
                    </span>
                    <span
                        v-if="savingsPercent > 0"
                        class="text-xs text-muted-foreground line-through"
                    >
                        ${{ normalPrice.toFixed(2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
