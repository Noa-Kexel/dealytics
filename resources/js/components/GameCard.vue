<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Flame, Heart } from 'lucide-vue-next';
import { computed, ref, nextTick } from 'vue';
import GameImage from '@/components/GameImage.vue';
import { useFavorites } from '@/composables/useFavorites';
import { platformName } from '@/lib/platforms';
import type { GameItem } from '@/types';

const props = defineProps<{
    game: GameItem;
}>();

const platformLabels = computed(() => {
    const seen = new Set<string>();

    for (const slug of props.game.platforms ?? []) {
        const name = platformName(slug);

        if (name) {
            seen.add(name);
        }
    }

    return Array.from(seen).slice(0, 3);
});

const discount = computed(() => Math.round(props.game.discount));
const thumb = computed(() => props.game.image || '');

function openGame() {
    router.visit(`/game/${props.game.id}`);
}

const { favoriteIds, toggleFavorite: toggle } = useFavorites();
const heartAnimating = ref(false);

const isFavorite = computed(() => favoriteIds.value.has(props.game.id));

async function toggleFavorite(e: Event) {
    e.stopPropagation();

    await toggle(props.game.id, props.game.title, thumb.value);

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
        @click="openGame"
    >
        <!-- Image — Nexarda covers are portrait (~3:4); match that ratio to avoid cropping titles -->
        <div class="relative aspect-[3/4] overflow-hidden bg-secondary">
            <GameImage
                :src="thumb"
                :alt="game.title"
                class="size-full object-cover object-top transition-transform duration-500 group-hover:scale-105"
            />

            <!-- Discount badge -->
            <div
                v-if="discount > 0"
                class="absolute top-2 right-2 rounded-md bg-dealytics-purple px-2 py-0.5 text-[11px] font-bold text-white"
            >
                -{{ discount }}%
            </div>

            <!-- Hot badge for big discounts -->
            <div
                v-if="discount >= 50"
                class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-md border border-dealytics-pink/30 bg-dealytics-pink/20 px-2 py-0.5 text-[10px] font-bold uppercase text-dealytics-pink"
            >
                <Flame class="size-2.5" />
                Hot
            </div>

        </div>

        <!-- Content -->
        <div class="p-3">
            <h3 class="truncate text-sm font-semibold text-foreground">
                {{ game.title }}
            </h3>

            <div class="mt-1 flex flex-wrap items-center gap-1">
                <span
                    v-for="p in platformLabels"
                    :key="p"
                    class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground"
                >
                    {{ p }}
                </span>
            </div>

            <div class="mt-2 flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-bold text-dealytics-cyan">
                        <template v-if="game.price === null">N/A</template>
                        <template v-else-if="game.price === 0">Gratuit</template>
                        <template v-else>{{ game.price.toFixed(2) }}€</template>
                    </span>
                    <span
                        v-if="discount > 0 && game.normalPrice"
                        class="text-xs text-muted-foreground line-through"
                    >
                        {{ game.normalPrice.toFixed(2) }}€
                    </span>
                </div>

                <div class="flex items-center">
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
                </div>
            </div>
        </div>
    </div>
</template>
