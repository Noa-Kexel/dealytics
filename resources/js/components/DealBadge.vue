<script setup lang="ts">
import { Flame, AlertTriangle, Snowflake } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    currentPrice: number;
    lowestPrice: number;
    normalPrice: number;
    savings: number;
    // True when the price has actually come down to the lowest value we have
    // tracked. Provided by the page so this badge can never contradict the
    // "au plus bas observé" note under the price history chart.
    atLowest: boolean;
}>();

// The badge answers "faut-il acheter maintenant ?", so it is driven by the
// tracked history — not by the store discount, which measures something else
// entirely (a permanent price cut shows 0 % off while still being an all-time
// low).
const badge = computed(() => {
    const ratio = props.lowestPrice > 0 ? props.currentPrice / props.lowestPrice : 1;

    // Nothing better to wait for: we have never observed this game cheaper.
    if (props.atLowest) {
        return {
            label: 'Excellent Deal',
            icon: Flame,
            class: 'border-dealytics-pink/60 text-dealytics-pink',
            iconClass: 'text-dealytics-pink',
            description: 'Prix au plus bas observé !',
        };
    }

    // The price has genuinely been cheaper before — worth waiting.
    if (ratio > 1.2) {
        return {
            label: 'Peut baisser',
            icon: AlertTriangle,
            class: 'border-yellow-500/60 text-yellow-400',
            iconClass: 'text-yellow-400',
            description: 'Le prix a déjà été plus bas.',
        };
    }

    // Close to the lowest without matching it, or not enough history to judge.
    return {
        label: 'Prix correct',
        icon: Snowflake,
        class: 'border-dealytics-cyan/60 text-dealytics-cyan',
        iconClass: 'text-dealytics-cyan',
        description:
            props.savings > 0
                ? 'Promotion intéressante mais pas exceptionnelle.'
                : 'Pas de baisse notable pour le moment.',
    };
});
</script>

<template>
    <div
        class="inline-flex items-center gap-2 rounded-lg border bg-black/70 px-3 py-1.5 backdrop-blur-md"
        :class="badge.class"
    >
        <component :is="badge.icon" class="size-4" :class="badge.iconClass" />
        <div>
            <span class="text-sm font-semibold">{{ badge.label }}</span>
            <p class="text-[10px] text-white/85">{{ badge.description }}</p>
        </div>
    </div>
</template>
