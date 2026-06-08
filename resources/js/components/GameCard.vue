<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Flame, Star, Heart } from 'lucide-vue-next';
import { computed, ref, nextTick } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useFavorites } from '@/composables/useFavorites';

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

const qualityPriceScore = computed(() => {
    const dealRating = parseFloat(props.deal.dealRating) || 0;
    const savings = parseFloat(props.deal.savings) || 0;
    const metacritic = parseFloat(props.deal.metacriticScore) || 0;
    const steamRating = parseFloat(props.deal.steamRatingPercent) || 0;

    const dealScore = Math.min(dealRating * 10, 100);
    const savingsScore = Math.min(savings * 1.2, 100);
    const qualityScore = metacritic > 0 ? metacritic : steamRating > 0 ? steamRating : 50;

    return Math.round(dealScore * 0.35 + savingsScore * 0.35 + qualityScore * 0.3);
});

const scoreColor = computed(() => {
    if (qualityPriceScore.value >= 80) {
        return 'text-dealytics-cyan border-dealytics-cyan/50 bg-dealytics-cyan/10';
    }

    if (qualityPriceScore.value >= 60) {
        return 'text-dealytics-purple border-dealytics-purple/50 bg-dealytics-purple/10';
    }

    if (qualityPriceScore.value >= 40) {
        return 'text-yellow-400 border-yellow-400/50 bg-yellow-400/10';
    }

    return 'text-muted-foreground border-border bg-secondary/50';
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

const scoreDetails = computed(() => {
    const dealRatingRaw = parseFloat(props.deal.dealRating) || 0;
    const savings = parseFloat(props.deal.savings) || 0;
    const metacritic = parseFloat(props.deal.metacriticScore) || 0;
    const steamRating = parseFloat(props.deal.steamRatingPercent) || 0;

    return {
        dealVal: Math.round(dealRatingRaw * 10),
        dealLabel: `${dealRatingRaw.toFixed(1)}/10`,
        savingsVal: Math.round(savings),
        savingsLabel: `${Math.round(savings)}%`,
        qualityVal: Math.round(metacritic > 0 ? metacritic : steamRating > 0 ? steamRating : 50),
        qualityLabel: metacritic > 0 ? `${Math.round(metacritic)}/100` : steamRating > 0 ? `${Math.round(steamRating)}%` : 'N/A',
        qualitySource: metacritic > 0 ? 'Metacritic' : steamRating > 0 ? 'Steam' : 'Qualité',
    };
});

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

// Build a higher-resolution Steam image from the thumb URL
const betterImage = computed(() => {
    const steamMatch = props.deal.thumb.match(/\/steam\/apps\/(\d+)\//);

    if (steamMatch) {
        return `https://cdn.cloudflare.steamstatic.com/steam/apps/${steamMatch[1]}/header.jpg`;
    }

    return props.deal.thumb;
});

function openDeal() {
    router.visit(`/game/${props.deal.gameID}`);
}

// Favorite logic (via composable — persists to DB when authenticated)
const { favoriteIds, toggleFavorite: toggle } = useFavorites();
const heartAnimating = ref(false);

const isFavorite = computed(() => favoriteIds.value.has(props.deal.gameID));

async function toggleFavorite(e: Event) {
    e.stopPropagation();

    await toggle(props.deal.gameID, props.deal.title, props.deal.thumb);

    // Trigger heart animation
    heartAnimating.value = false;
    await nextTick();
    heartAnimating.value = true;
    setTimeout(() => {
        heartAnimating.value = false;
    }, 400);
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
                :src="betterImage"
                :alt="deal.title"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                loading="lazy"
                @error="($event.target as HTMLImageElement).src = deal.thumb"
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
                <div class="flex items-center gap-1.5">
                    <!-- Favorite button -->
                    <button
                        class="flex size-8 items-center justify-center rounded-full border border-border/50 bg-secondary/50 transition-all duration-200 hover:bg-secondary active:scale-90"
                        @click="toggleFavorite"
                    >
                        <Heart
                            class="size-3.5 transition-all duration-300"
                            :class="[
                                isFavorite ? 'fill-dealytics-pink text-dealytics-pink' : 'text-muted-foreground hover:text-foreground',
                                heartAnimating ? 'animate-heart-pop' : '',
                            ]"
                        />
                    </button>
                    <TooltipProvider :delay-duration="200">
                        <Tooltip>
                            <TooltipTrigger as-child @click.stop>
                                <div
                                    class="flex size-8 cursor-help items-center justify-center rounded-full border text-[11px] font-bold transition-transform hover:scale-110"
                                    :class="scoreColor"
                                >
                                    {{ qualityPriceScore }}
                                </div>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            :side-offset="8"
                            class="w-52 border-border/50 bg-card p-3 text-card-foreground shadow-xl"
                        >
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold" :class="scoreColor.split(' ')[0]">{{ scoreLabel }}</span>
                                    <span class="text-xs font-bold" :class="scoreColor.split(' ')[0]">{{ qualityPriceScore }}/100</span>
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
                </div>
            </div>
        </div>
    </div>
</template>
