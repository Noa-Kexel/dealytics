<script setup lang="ts">
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
} from 'chart.js';
import type { ChartOptions, TooltipItem } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip);

interface OfferPoint {
    store: string;
    price: number;
    official: boolean;
}

const props = defineProps<{
    offers: OfferPoint[];
    currencySymbol: string;
}>();

// Cheapest offer per store, sorted ascending, capped to keep the chart readable.
const points = computed(() => {
    const byStore = new Map<string, OfferPoint>();

    for (const offer of props.offers) {
        const existing = byStore.get(offer.store);

        if (!existing || offer.price < existing.price) {
            byStore.set(offer.store, offer);
        }
    }

    return Array.from(byStore.values())
        .sort((a, b) => a.price - b.price)
        .slice(0, 8);
});

const chartData = computed(() => ({
    labels: points.value.map((p) => p.store),
    datasets: [
        {
            label: `Prix (${props.currencySymbol})`,
            data: points.value.map((p) => p.price),
            backgroundColor: points.value.map((p) =>
                p.official ? 'rgba(34, 211, 238, 0.55)' : 'rgba(168, 85, 247, 0.55)',
            ),
            borderColor: points.value.map((p) =>
                p.official ? '#22D3EE' : '#A855F7',
            ),
            borderWidth: 1,
            borderRadius: 6,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgba(6, 4, 15, 0.9)',
            borderColor: 'rgba(168, 85, 247, 0.3)',
            borderWidth: 1,
            titleColor: '#f2f2f2',
            bodyColor: '#f2f2f2',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx: TooltipItem<'bar'>) =>
                    `${(ctx.parsed.y ?? 0).toFixed(2)}${props.currencySymbol}`,
            },
        },
    },
    scales: {
        x: {
            grid: {
                color: 'rgba(124, 58, 237, 0.08)',
            },
            ticks: {
                color: 'rgba(255,255,255,0.4)',
                font: { size: 10 },
                maxRotation: 45,
                minRotation: 0,
            },
        },
        y: {
            grid: {
                color: 'rgba(124, 58, 237, 0.08)',
            },
            ticks: {
                color: 'rgba(255,255,255,0.4)',
                font: { size: 10 },
                callback: (value: string | number) => `${value}${props.currencySymbol}`,
            },
        },
    },
}));
</script>

<template>
    <div class="h-64 w-full">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
