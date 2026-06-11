<script setup lang="ts">
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Filler,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler);

interface PricePoint {
    date: number;
    price: number;
    store: string;
}

const props = defineProps<{
    priceHistory: PricePoint[];
    currentPrice: number;
}>();

const chartData = computed(() => {
    const sorted = [...props.priceHistory].sort((a, b) => a.date - b.date);

    return {
        labels: sorted.map((p) =>
            new Date(p.date * 1000).toLocaleDateString('fr-FR', {
                month: 'short',
                year: '2-digit',
            }),
        ),
        datasets: [
            {
                label: 'Prix (€)',
                data: sorted.map((p) => p.price),
                borderColor: '#A855F7',
                backgroundColor: 'rgba(168, 85, 247, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#A855F7',
                pointBorderColor: '#A855F7',
                pointRadius: 3,
                pointHoverRadius: 6,
                borderWidth: 2,
            },
            {
                label: 'Prix actuel',
                data: sorted.map(() => props.currentPrice),
                borderColor: '#22D3EE',
                borderDash: [5, 5],
                borderWidth: 1,
                pointRadius: 0,
                fill: false,
            },
        ],
    };
});

const chartOptions = {
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
                label: (ctx: { dataset: { label: string }; parsed: { y: number } }) =>
                    `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}€`,
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
            },
        },
        y: {
            grid: {
                color: 'rgba(124, 58, 237, 0.08)',
            },
            ticks: {
                color: 'rgba(255,255,255,0.4)',
                font: { size: 10 },
                callback: (value: string | number) => `${value}€`,
            },
        },
    },
};
</script>

<template>
    <div class="h-64 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
