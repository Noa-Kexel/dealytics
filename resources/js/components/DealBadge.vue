<script setup lang="ts">
import { Flame, AlertTriangle, Snowflake } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    currentPrice: number;
    lowestPrice: number;
    savings: number;
    /** True si le prix actuel est au plus bas observé (historique). */
    atLowest: boolean;
}>();

// Badge basé sur l'historique suivi, pas sur la remise magasin.
const badge = computed(() => {
    const ratio = props.lowestPrice > 0 ? props.currentPrice / props.lowestPrice : 1;

    if (props.atLowest) {
        return {
            label: 'Excellent deal',
            icon: Flame,
            class: 'border-dealytics-pink/60 text-dealytics-pink',
            iconClass: 'text-dealytics-pink',
            description: 'Prix au plus bas observé !',
        };
    }

    if (ratio > 1.2) {
        return {
            label: 'Peut baisser',
            icon: AlertTriangle,
            class: 'border-yellow-500/60 text-yellow-400',
            iconClass: 'text-yellow-400',
            description: 'Le prix a déjà été plus bas.',
        };
    }

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
