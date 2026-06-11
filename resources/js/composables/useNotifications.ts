import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { api } from '@/lib/api';

export interface AppNotification {
    id: string;
    type: string;
    game_id?: string;
    title?: string;
    target_price?: number;
    current_price?: number;
    message: string;
    read_at: string | null;
    created_at: string;
}

const notifications = ref<AppNotification[]>([]);
const unreadCount = ref(0);
const loaded = ref(false);

export function useNotifications() {
    const page = usePage();
    const authenticated = !!(page.props as { auth?: { user?: unknown } })?.auth?.user;

    async function loadNotifications(): Promise<void> {
        if (!authenticated) {
            notifications.value = [];
            unreadCount.value = 0;
            loaded.value = true;

            return;
        }

        try {
            const [list, countData] = await Promise.all([
                api<AppNotification[]>('/api/notifications'),
                api<{ count: number }>('/api/notifications/unread-count'),
            ]);

            notifications.value = list;
            unreadCount.value = countData.count;
        } catch {
            // keep current state
        }

        loaded.value = true;
    }

    async function markAsRead(id: string): Promise<void> {
        if (!authenticated) {
            return;
        }

        try {
            await api(`/api/notifications/${id}/read`, { method: 'PATCH' });

            const notification = notifications.value.find((n) => n.id === id);

            if (notification && !notification.read_at) {
                notification.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        } catch {
            // ignore
        }
    }

    async function markAllAsRead(): Promise<void> {
        if (!authenticated) {
            return;
        }

        try {
            await api('/api/notifications/read-all', { method: 'POST' });
            notifications.value.forEach((n) => {
                n.read_at = n.read_at ?? new Date().toISOString();
            });
            unreadCount.value = 0;
        } catch {
            // ignore
        }
    }

    function getUnread(): AppNotification[] {
        return notifications.value.filter((n) => !n.read_at);
    }

    return {
        notifications,
        unreadCount,
        loaded,
        loadNotifications,
        markAsRead,
        markAllAsRead,
        getUnread,
    };
}
