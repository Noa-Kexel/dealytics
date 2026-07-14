<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

export interface ToastAlert {
    gameId: string;
    title: string;
    currentPrice: number;
    targetPrice: number;
}

const toasts = ref<ToastAlert[]>([]);
const mounted = ref(false);

function show(toast: ToastAlert) {
    toasts.value.push(toast);

    setTimeout(() => dismiss(toast.gameId), 8000);
}

function dismiss(gameId: string) {
    toasts.value = toasts.value.filter((t) => t.gameId !== gameId);
}

onMounted(() => {
    mounted.value = true;

    window.addEventListener('dealytics:alert-triggered', ((event: CustomEvent<ToastAlert>) => {
        show(event.detail);
    }) as EventListener);
});

defineExpose({ show });
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div class="pointer-events-none fixed right-4 bottom-4 z-100 flex flex-col gap-2">
            <div
                v-for="toast in toasts"
                :key="toast.gameId"
                class="pointer-events-auto w-80 animate-in slide-in-from-right-5 overflow-hidden rounded-xl border border-dealytics-cyan/30 bg-background/95 shadow-xl backdrop-blur-xl"
            >
                <div class="flex items-start gap-3 p-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-dealytics-cyan/15">
                        <Bell class="size-4 text-dealytics-cyan" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-dealytics-cyan">Prix cible atteint !</p>
                        <p class="mt-0.5 text-xs leading-snug text-foreground">
                            {{ toast.title }} est à {{ toast.currentPrice.toFixed(2) }}€
                            <span class="text-muted-foreground">(objectif : {{ toast.targetPrice.toFixed(2) }}€)</span>
                        </p>
                        <Link
                            :href="`/game/${toast.gameId}`"
                            class="mt-2 inline-block text-xs font-medium text-dealytics-purple hover:text-dealytics-pink"
                        >
                            Voir l'offre →
                        </Link>
                    </div>
                    <button
                        class="shrink-0 text-muted-foreground/50 transition-colors hover:text-foreground"
                        @click="dismiss(toast.gameId)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
