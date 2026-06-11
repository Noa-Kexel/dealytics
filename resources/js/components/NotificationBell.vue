<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell, CheckCheck, ExternalLink } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/composables/useNotifications';

const {
    notifications,
    unreadCount,
    loadNotifications,
    markAsRead,
    markAllAsRead,
} = useNotifications();

const hasUnread = computed(() => unreadCount.value > 0);

function formatDate(iso: string): string {
    const date = new Date(iso);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

    if (diffHours < 1) {
        return 'À l\'instant';
    }

    if (diffHours < 24) {
        return `Il y a ${diffHours}h`;
    }

    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

async function handleOpenNotification(id: string, gameId?: string) {
    await markAsRead(id);

    if (gameId) {
        router.visit(`/game/${gameId}`);
    }
}

function goToAlerts() {
    router.visit('/alerts');
}

onMounted(() => {
    loadNotifications();

    window.addEventListener('dealytics:notifications-changed', () => {
        loadNotifications();
    });
});

defineExpose({ loadNotifications });
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative size-9 rounded-full"
                aria-label="Notifications"
            >
                <Bell class="size-4" />
                <span
                    v-if="hasUnread"
                    class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-dealytics-pink text-[9px] font-bold text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0">
            <div class="flex items-center justify-between border-b border-border/50 px-4 py-3">
                <span class="text-sm font-semibold">Notifications</span>
                <button
                    v-if="hasUnread"
                    class="flex items-center gap-1 text-[10px] text-muted-foreground transition-colors hover:text-foreground"
                    @click="markAllAsRead"
                >
                    <CheckCheck class="size-3" />
                    Tout marquer lu
                </button>
            </div>

            <div v-if="notifications.length === 0" class="px-4 py-8 text-center">
                <Bell class="mx-auto mb-2 size-6 text-muted-foreground/40" />
                <p class="text-xs text-muted-foreground">Aucune notification</p>
                <p class="mt-1 text-[10px] text-muted-foreground/70">
                    Définissez une alerte prix sur un jeu pour être notifié.
                </p>
            </div>

            <div v-else class="max-h-80 overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="flex w-full items-start gap-3 border-b border-border/30 px-4 py-3 text-left transition-colors last:border-0 hover:bg-secondary/40"
                    :class="!notification.read_at ? 'bg-dealytics-purple/5' : ''"
                    @click="handleOpenNotification(notification.id, notification.game_id)"
                >
                    <div
                        class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full"
                        :class="notification.type === 'price_alert'
                            ? 'bg-dealytics-cyan/15 text-dealytics-cyan'
                            : 'bg-secondary text-muted-foreground'"
                    >
                        <Bell class="size-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs leading-snug" :class="!notification.read_at ? 'font-medium' : ''">
                            {{ notification.message }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ formatDate(notification.created_at) }}
                        </p>
                    </div>
                    <ExternalLink
                        v-if="notification.game_id"
                        class="mt-1 size-3 shrink-0 text-muted-foreground/50"
                    />
                </button>
            </div>

            <div class="border-t border-border/50 p-1">
                <DropdownMenuItem
                    class="cursor-pointer justify-center rounded-lg py-2 text-xs text-muted-foreground"
                    @select="goToAlerts"
                >
                    Voir mes alertes
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
