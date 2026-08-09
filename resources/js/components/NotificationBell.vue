<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, CheckCheck, ExternalLink, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/composables/useNotifications';

const open = ref(false);

const {
    notifications,
    unreadCount,
    loadNotifications,
    markAsRead,
    markAllAsRead,
    deleteNotification,
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

async function handleOpenNotification(id: string) {
    await markAsRead(id);
    open.value = false;
}

onMounted(() => {
    loadNotifications();

    window.addEventListener('dealytics:notifications-changed', () => {
        loadNotifications();
    });
});
</script>

<template>
    <DropdownMenu v-model:open="open">
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
                    type="button"
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
                    Vous serez notifié quand un prix cible est atteint.
                </p>
            </div>

            <div v-else class="max-h-80 overflow-y-auto">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="group/notif flex items-start border-b border-border/30 transition-colors last:border-0 hover:bg-secondary/40"
                    :class="!notification.read_at ? 'bg-dealytics-purple/5' : ''"
                >
                    <Link
                        v-if="notification.game_id"
                        :href="`/game/${notification.game_id}`"
                        class="flex min-w-0 flex-1 items-start gap-3 py-3 pr-1 pl-4 text-left"
                        @click="handleOpenNotification(notification.id)"
                    >
                        <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-dealytics-cyan/15 text-dealytics-cyan">
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
                        <ExternalLink class="mt-1 size-3 shrink-0 text-muted-foreground/50" />
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="flex min-w-0 flex-1 items-start gap-3 py-3 pr-1 pl-4 text-left"
                        @click="handleOpenNotification(notification.id)"
                    >
                        <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-dealytics-cyan/15 text-dealytics-cyan">
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
                    </button>

                    <button
                        type="button"
                        class="mt-3 mr-2 flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground/40 transition-colors hover:bg-dealytics-pink/10 hover:text-dealytics-pink group-hover/notif:text-muted-foreground/70"
                        aria-label="Supprimer la notification"
                        title="Supprimer"
                        @click.stop.prevent="deleteNotification(notification.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
