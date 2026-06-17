<script setup lang="ts">
import {
    Chart as ChartJS,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Filler,
} from 'chart.js';
import type {
    ChartOptions,
    Plugin,
    ScriptableContext,
    TooltipItem,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(LinearScale, PointElement, LineElement, Tooltip, Filler);

interface PricePoint {
    date: number; // unix seconds
    price: number;
    store: string;
}

type RangeKey = '1m' | '3m' | '1y' | 'all';

const props = withDefaults(
    defineProps<{
        priceHistory: PricePoint[];
        currentPrice: number;
        currencySymbol?: string;
        rangeKey?: RangeKey;
    }>(),
    { currencySymbol: '€', rangeKey: 'all' },
);

const PURPLE = '#A855F7';
const CYAN = '#22D3EE';
const PINK = '#EC4899';

const sorted = computed(() =>
    [...props.priceHistory].sort((a, b) => a.date - b.date),
);

const bounds = computed(() => {
    const pts = sorted.value;

    if (!pts.length) {
        return { minX: 0, maxX: 0, lo: 0, hi: 0, lowest: 0, spanDays: 0, varied: false };
    }

    const xs = pts.map((p) => p.date * 1000);
    const ys = pts.map((p) => p.price);
    const minX = xs[0];
    const maxX = xs[xs.length - 1];
    const min = Math.min(...ys);
    const max = Math.max(...ys);

    // Frame the visible series with a little breathing room so the line never
    // glues itself to the top/bottom edge.
    let lo: number;
    let hi: number;

    if (max === min) {
        const pad = Math.max(min * 0.15, 1);
        lo = min - pad;
        hi = max + pad;
    } else {
        const pad = (max - min) * 0.2;
        lo = min - pad;
        hi = max + pad;
    }

    return {
        minX,
        maxX,
        lo: Math.max(0, lo),
        hi,
        lowest: min,
        spanDays: (maxX - minX) / 86_400_000,
        varied: max > min,
    };
});

// Calendar-aware ticks (aligned to days for short ranges, to month starts for
// long ones) so labels read cleanly instead of landing on arbitrary dates.
const xTicks = computed<number[]>(() => {
    const { minX, maxX, spanDays } = bounds.value;

    if (minX === maxX) {
        return [minX];
    }

    const out: number[] = [];

    if (spanDays <= 92) {
        const n = 6;
        const step = (maxX - minX) / n;

        for (let i = 0; i <= n; i++) {
            const d = new Date(minX + step * i);
            d.setHours(0, 0, 0, 0);
            out.push(d.getTime());
        }
    } else {
        const months = Math.max(1, Math.round(spanDays / 30.44));
        const monthStep = Math.max(1, Math.round(months / 6));
        const d = new Date(minX);
        d.setDate(1);
        d.setHours(0, 0, 0, 0);

        if (d.getTime() < minX) {
            d.setMonth(d.getMonth() + 1);
        }

        while (d.getTime() <= maxX) {
            out.push(d.getTime());
            d.setMonth(d.getMonth() + monthStep);
        }
    }

    return Array.from(new Set(out)).filter((t) => t >= minX - 1 && t <= maxX + 1);
});

function formatTick(ms: number): string {
    const d = new Date(ms);

    if (bounds.value.spanDays <= 92) {
        return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }

    return d.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
}

// Round a raw interval up to a human-friendly step (1, 2, 5, 10, 20, 50…).
function niceStep(raw: number): number {
    const exp = Math.floor(Math.log10(raw));
    const base = 10 ** exp;
    const f = raw / base;

    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * base;
}

// Round Y ticks (kept strictly inside the tight min/max frame so the line keeps
// its full amplitude while the labels stay on clean values).
const yTicks = computed<number[]>(() => {
    const { lo, hi } = bounds.value;

    if (hi <= lo) {
        return [lo];
    }

    const step = niceStep((hi - lo) / 4);
    const out: number[] = [];

    for (let v = Math.ceil(lo / step) * step; v <= hi; v += step) {
        out.push(Number(v.toFixed(2)));
    }

    return out.length ? out : [lo, hi];
});

const chartData = computed(() => ({
    datasets: [
        {
            label: 'Prix',
            data: sorted.value.map((p) => ({
                x: p.date * 1000,
                y: p.price,
                store: p.store,
            })),
            borderColor: (ctx: ScriptableContext<'line'>) => {
                const area = ctx.chart.chartArea;

                if (!area) {
                    return PURPLE;
                }

                const g = ctx.chart.ctx.createLinearGradient(
                    area.left,
                    0,
                    area.right,
                    0,
                );
                g.addColorStop(0, CYAN);
                g.addColorStop(1, PURPLE);

                return g;
            },
            backgroundColor: (ctx: ScriptableContext<'line'>) => {
                const area = ctx.chart.chartArea;

                if (!area) {
                    return 'rgba(168, 85, 247, 0.15)';
                }

                const g = ctx.chart.ctx.createLinearGradient(
                    0,
                    area.top,
                    0,
                    area.bottom,
                );
                g.addColorStop(0, 'rgba(168, 85, 247, 0.35)');
                g.addColorStop(0.6, 'rgba(168, 85, 247, 0.08)');
                g.addColorStop(1, 'rgba(168, 85, 247, 0)');

                return g;
            },
            fill: true,
            stepped: 'after' as const,
            borderWidth: 2.5,
            borderJoinStyle: 'round' as const,
            borderCapStyle: 'round' as const,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: '#ffffff',
            pointHoverBorderColor: CYAN,
            pointHoverBorderWidth: 2,
        },
    ],
}));

// Dashed reference line at the lowest price in the visible window, with a label.
const lowestLine: Plugin<'line'> = {
    id: 'lowestLine',
    afterDatasetsDraw(chart) {
        const { lowest, varied } = bounds.value;

        if (!varied) {
            return;
        }

        const yScale = chart.scales.y;
        const { left, right } = chart.chartArea;
        const y = yScale.getPixelForValue(lowest);
        const { ctx } = chart;

        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([5, 5]);
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(236, 72, 153, 0.45)';
        ctx.moveTo(left, y);
        ctx.lineTo(right, y);
        ctx.stroke();

        ctx.setLineDash([]);
        ctx.font = '600 9px ui-sans-serif, system-ui, sans-serif';
        ctx.fillStyle = PINK;
        ctx.textBaseline = 'bottom';
        ctx.fillText(
            `min ${lowest.toFixed(2)}${props.currencySymbol}`,
            left + 4,
            y - 3,
        );
        ctx.restore();
    },
};

// Vertical crosshair following the hovered point.
const crosshair: Plugin<'line'> = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements?.() ?? [];

        if (!active.length) {
            return;
        }

        const x = active[0].element.x;
        const { top, bottom } = chart.chartArea;
        const { ctx } = chart;

        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(168, 85, 247, 0.4)';
        ctx.moveTo(x, top);
        ctx.lineTo(x, bottom);
        ctx.stroke();
        ctx.restore();
    },
};

// Glowing dot at the latest price (turns pink when the price is at its lowest).
const endpointGlow: Plugin<'line'> = {
    id: 'endpointGlow',
    afterDatasetsDraw(chart) {
        const meta = chart.getDatasetMeta(0);

        if (!meta?.data?.length) {
            return;
        }

        const pt = meta.data[meta.data.length - 1];
        const { lowest, varied } = bounds.value;
        const atLow =
            varied && props.currentPrice > 0 && props.currentPrice <= lowest * 1.02;
        const color = atLow ? PINK : CYAN;
        const { ctx } = chart;

        ctx.save();
        ctx.beginPath();
        ctx.arc(pt.x, pt.y, 8, 0, Math.PI * 2);
        ctx.fillStyle = atLow ? 'rgba(236, 72, 153, 0.22)' : 'rgba(34, 211, 238, 0.22)';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(pt.x, pt.y, 3.5, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.strokeStyle = '#06040F';
        ctx.lineWidth = 1.5;
        ctx.fill();
        ctx.stroke();
        ctx.restore();
    },
};

const plugins = [lowestLine, crosshair, endpointGlow];

const chartOptions = computed<ChartOptions<'line'>>(() => {
    const { minX, maxX, lo, hi } = bounds.value;
    const ticks = xTicks.value;

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        layout: { padding: { top: 10, right: 10, bottom: 0, left: 2 } },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(6, 4, 15, 0.92)',
                borderColor: 'rgba(168, 85, 247, 0.35)',
                borderWidth: 1,
                titleColor: '#f2f2f2',
                bodyColor: '#f2f2f2',
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                titleFont: { size: 11, weight: 'normal' },
                bodyFont: { size: 14, weight: 'bold' },
                callbacks: {
                    title: (items: TooltipItem<'line'>[]) =>
                        items.length
                            ? new Date(items[0].parsed.x ?? 0).toLocaleDateString(
                                  'fr-FR',
                                  { day: 'numeric', month: 'long', year: 'numeric' },
                              )
                            : '',
                    label: (ctx: TooltipItem<'line'>) =>
                        `${(ctx.parsed.y ?? 0).toFixed(2)}${props.currencySymbol}`,
                    afterLabel: (ctx: TooltipItem<'line'>) => {
                        const store = (ctx.raw as { store?: string })?.store;

                        return store && store !== 'Maintenant' ? store : '';
                    },
                },
            },
        },
        scales: {
            x: {
                type: 'linear',
                min: minX,
                max: maxX,
                border: { display: false },
                grid: { display: false },
                afterBuildTicks: (axis) => {
                    axis.ticks = ticks.map((value) => ({ value }));
                },
                ticks: {
                    color: 'rgba(255,255,255,0.45)',
                    font: { size: 10 },
                    maxRotation: 0,
                    autoSkipPadding: 8,
                    callback: (value: string | number) => formatTick(Number(value)),
                },
            },
            y: {
                min: lo,
                max: hi,
                border: { display: false },
                grid: { color: 'rgba(124, 58, 237, 0.1)', drawTicks: false },
                afterBuildTicks: (axis) => {
                    axis.ticks = yTicks.value.map((value) => ({ value }));
                },
                ticks: {
                    color: 'rgba(255,255,255,0.45)',
                    font: { size: 10 },
                    padding: 8,
                    callback: (value: string | number) =>
                        `${Number(value).toFixed(0)}${props.currencySymbol}`,
                },
            },
        },
    };
});
</script>

<template>
    <div class="h-64 w-full sm:h-72">
        <Line :data="chartData" :options="chartOptions" :plugins="plugins" />
    </div>
</template>
