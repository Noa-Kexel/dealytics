<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bell, ExternalLink, Gamepad2, Trash2 } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useAlerts } from '@/composables/useAlerts';

const {
    alerts,
    loadAlerts,
    getActiveAlerts,
    getReachedAlerts,
    removeAlert,
    checkAlerts,
} = useAlerts();

function formatPrice(value?: number): string {
    return (value ?? 0).toFixed(2);
}

function gameId(alert: { game_id?: string; gameID?: string }): string {
    return alert.game_id || alert.gameID || '';
}

onMounted(async () => {
    await loadAlerts();
    await checkAlerts();
});
</script>

<template>
    <Head title="Mes alertes" />

    <div class="animate-page-in mx-auto max-w-3xl px-4 py-6 lg:px-6">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-cyan/20">
                <Bell class="size-5 text-dealytics-cyan" />
            </div>
            <div>
                <h1 class="font-heading text-3xl font-bold text-foreground">Mes alertes</h1>
                <p class="text-sm text-muted-foreground">
                    Jeux surveillés et objectifs de prix
                </p>
            </div>
        </div>

        <!-- Reached -->
        <div v-if="getReachedAlerts().length > 0" class="mb-6">
            <p class="mb-3 text-[10px] font-medium uppercase tracking-wider text-dealytics-pink">
                Objectif atteint
            </p>
            <div class="space-y-2">
                <div
                    v-for="alert in getReachedAlerts()"
                    :key="gameId(alert)"
                    class="flex items-center justify-between rounded-xl border border-dealytics-pink/20 bg-dealytics-pink/10 px-4 py-3"
                >
                    <div>
                        <div class="text-sm font-medium">{{ alert.title }}</div>
                        <div class="text-xs text-dealytics-pink">
                            Objectif {{ formatPrice(alert.targetPrice ?? alert.target_price) }}€ atteint !
                            <span v-if="alert.currentPrice ?? alert.current_price" class="text-muted-foreground">
                                · Actuel : {{ formatPrice(alert.currentPrice ?? alert.current_price) }}€
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/game/${gameId(alert)}`"
                            class="text-dealytics-cyan hover:text-dealytics-cyan/80"
                        >
                            <ExternalLink class="size-4" />
                        </Link>
                        <button
                            class="text-muted-foreground/50 hover:text-red-400"
                            @click="removeAlert(gameId(alert))"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active -->
        <div v-if="getActiveAlerts().length > 0" class="mb-6">
            <p class="mb-3 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                En surveillance
            </p>
            <div class="space-y-2">
                <div
                    v-for="alert in getActiveAlerts()"
                    :key="gameId(alert)"
                    class="flex items-center justify-between rounded-xl border border-border/50 bg-secondary/30 px-4 py-3"
                >
                    <div>
                        <div class="text-sm font-medium">{{ alert.title }}</div>
                        <div class="text-xs text-muted-foreground">
                            Objectif : {{ formatPrice(alert.targetPrice ?? alert.target_price) }}€
                            <span v-if="alert.currentPrice ?? alert.current_price">
                                · Actuel : {{ formatPrice(alert.currentPrice ?? alert.current_price) }}€
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/game/${gameId(alert)}`"
                            class="text-dealytics-cyan hover:text-dealytics-cyan/80"
                        >
                            <ExternalLink class="size-4" />
                        </Link>
                        <button
                            class="text-muted-foreground/50 hover:text-red-400"
                            @click="removeAlert(gameId(alert))"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="alerts.length === 0"
            class="border-gradient rounded-xl px-6 py-12 text-center"
        >
            <Bell class="mx-auto mb-3 size-10 text-muted-foreground/30" />
            <h2 class="font-heading text-lg font-semibold">Aucune alerte active</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Parcourez le catalogue et définissez un prix cible sur la page d'un jeu.
            </p>
            <Button
                as-child
                class="mt-6 bg-dealytics-purple hover:bg-dealytics-deep-purple"
            >
                <Link href="/">
                    <Gamepad2 class="size-4" />
                    Explorer les jeux
                </Link>
            </Button>
        </div>
    </div>
</template>
