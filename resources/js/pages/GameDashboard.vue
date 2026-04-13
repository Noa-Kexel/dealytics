<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Star,
    Bell,
    TrendingDown,
    Trophy,
    Calendar,
    DollarSign,
    Target,
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';

const favorites = ref<{ gameID: string; title: string; thumb: string; addedAt: string }[]>([]);

onMounted(() => {
    try {
        favorites.value = JSON.parse(localStorage.getItem('dealytics_favorites') || '[]');
    } catch {
        favorites.value = [];
    }
});

const budget = ref(
    (() => {
        try {
            return JSON.parse(localStorage.getItem('dealytics_budget') || '{"limit": 150, "spent": 0}');
        } catch {
            return { limit: 150, spent: 0 };
        }
    })()
);

const budgetPercent = computed(() =>
    budget.value.limit > 0
        ? Math.min(100, Math.round((budget.value.spent / budget.value.limit) * 100))
        : 0
);

const remaining = computed(() => Math.max(0, budget.value.limit - budget.value.spent));
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Header -->
        <div class="mb-8 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-purple/20">
                <Trophy class="size-5 text-dealytics-purple" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-heading text-3xl font-bold text-foreground">Dashboard</h1>
                    <span class="rounded-full bg-dealytics-cyan/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-dealytics-cyan">
                        PRO
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">Votre centre de commande gaming</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Star class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-dealytics-purple">{{ favorites.length }}</div>
                <div class="text-xs text-muted-foreground">Jeux suivis</div>
                <div class="text-[10px] text-muted-foreground/70">dans la watchlist</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <Bell class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-foreground">0</div>
                <div class="text-xs text-muted-foreground">Alertes actives</div>
                <div class="text-[10px] text-muted-foreground/70">surveillance prix</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-dealytics-cyan">--</div>
                <div class="text-xs text-muted-foreground">Réduction moy.</div>
                <div class="text-[10px] text-muted-foreground/70">sur tous les deals</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-pink/20">
                    <DollarSign class="size-4 text-dealytics-pink" />
                </div>
                <div class="text-2xl font-bold text-dealytics-pink">$0</div>
                <div class="text-xs text-muted-foreground">Economisé</div>
                <div class="text-[10px] text-muted-foreground/70">ce mois-ci</div>
            </div>
        </div>

        <!-- Budget + Spending -->
        <div class="mb-6 grid gap-4 md:grid-cols-2">
            <!-- Monthly Budget -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Calendar class="size-4 text-dealytics-purple" />
                        <h2 class="font-heading text-lg font-semibold">Budget Mensuel</h2>
                    </div>
                    <span class="text-xs text-muted-foreground uppercase">
                        {{ new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) }}
                    </span>
                </div>

                <div class="mb-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-dealytics-cyan">${{ budget.spent.toFixed(2) }}</span>
                    <span class="text-sm text-muted-foreground">/ ${{ budget.limit }}</span>
                </div>

                <!-- Progress bar -->
                <div class="mb-2 h-2 overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="budgetPercent > 80 ? 'bg-dealytics-pink' : 'bg-dealytics-cyan'"
                        :style="{ width: `${budgetPercent}%` }"
                    />
                </div>

                <p class="text-xs text-muted-foreground">
                    {{ 100 - budgetPercent }}% restant · ${{ remaining.toFixed(2) }} disponible
                </p>

                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold text-foreground">0</div>
                        <div class="text-[10px] text-muted-foreground">Achetés</div>
                    </div>
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold text-dealytics-cyan">$0</div>
                        <div class="text-[10px] text-muted-foreground">Economisé</div>
                    </div>
                    <div class="rounded-lg bg-secondary/50 p-2 text-center">
                        <div class="text-sm font-semibold text-dealytics-pink">${{ remaining.toFixed(2) }}</div>
                        <div class="text-[10px] text-muted-foreground">Restant</div>
                    </div>
                </div>
            </div>

            <!-- Placeholder Spending History -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-4 flex items-center gap-2">
                    <TrendingDown class="size-4 text-dealytics-cyan" />
                    <h2 class="font-heading text-lg font-semibold">Historique Dépenses</h2>
                </div>
                <div class="flex h-48 items-center justify-center text-muted-foreground/30">
                    <p class="text-sm">Graphique disponible bientôt</p>
                </div>
            </div>
        </div>

        <!-- Top Deals -->
        <div class="border-gradient rounded-xl p-6">
            <div class="mb-4 flex items-center gap-2">
                <Target class="size-4 text-dealytics-pink" />
                <h2 class="font-heading text-lg font-semibold">Top Offres du Moment</h2>
            </div>
            <p class="text-sm text-muted-foreground">
                Consultez la page de recherche pour voir les meilleures offres en temps réel.
            </p>
        </div>
    </div>
</template>
