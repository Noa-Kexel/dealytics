<script setup lang="ts">
import { Flame, AlertTriangle, Snowflake } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    currentPrice: number;
    lowestPrice: number;
    normalPrice: number;
    savings: number;
}>();

const badge = computed(() => {
    const ratio = props.lowestPrice > 0 ? props.currentPrice / props.lowestPrice : 1;
    const savingsPercent = props.savings;

    // Current price is at or near the historical lowest
    if (ratio <= 1.05 && savingsPercent >= 40) {
        return {
            label: 'Excellent Deal',
            icon: Flame,
            class: 'border-dealytics-pink/60 text-dealytics-pink',
            iconClass: 'text-dealytics-pink',
            description: 'Prix au plus bas historique !',
        };
    }

    // Price can still go lower
    if (ratio > 1.2 || savingsPercent < 25) {
        return {
            label: 'Peut baisser',
            icon: AlertTriangle,
            class: 'border-yellow-500/60 text-yellow-400',
            iconClass: 'text-yellow-400',
            description: 'Le prix a déjà été plus bas.',
        };
    }

    // Normal/decent price
    return {
        label: 'Prix correct',
        icon: Snowflake,
        class: 'border-dealytics-cyan/60 text-dealytics-cyan',
        iconClass: 'text-dealytics-cyan',
        description: 'Promotion intéressante mais pas exceptionnelle.',
    };
});
</script>

<template>
    <div
        class="inline-flex items-center gap-2 rounded-lg border bg-black/50 px-3 py-1.5 backdrop-blur-md"
        :class="badge.class"
    >
        <component :is="badge.icon" class="size-4" :class="badge.iconClass" />
        <div>
            <span class="text-sm font-semibold">{{ badge.label }}</span>
            <p class="text-[10px] opacity-90">{{ badge.description }}</p>
        </div>
    </div>
</template>
