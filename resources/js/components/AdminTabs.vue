<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Users, Bell, Mail } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface AdminTab {
    label: string;
    href: string;
    icon: LucideIcon;
    // Prop partagée qui alimente la pastille de comptage, si l'onglet en a une.
    badge?: 'contactUnread';
}

const tabs: AdminTab[] = [
    { label: 'Utilisateurs', href: '/admin/users', icon: Users },
    { label: 'Contact', href: '/admin/contact', icon: Mail, badge: 'contactUnread' },
    { label: 'Test notifications', href: '/admin/notifications', icon: Bell },
];

const page = usePage<{ contactUnread?: number }>();

const currentPath = computed(() => {
    try {
        return new URL(page.url, window.location.origin).pathname;
    } catch {
        return page.url;
    }
});

function isActive(href: string): boolean {
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
}

function badgeCount(tab: AdminTab): number {
    return tab.badge ? (page.props[tab.badge] ?? 0) : 0;
}
</script>

<template>
    <div class="mb-6 flex flex-wrap gap-1.5 border-b border-border/40 pb-3">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
            :class="isActive(tab.href)
                ? 'bg-dealytics-purple/15 text-dealytics-purple'
                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'"
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.label }}
            <span
                v-if="badgeCount(tab) > 0"
                class="ml-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-dealytics-pink px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
            >
                {{ badgeCount(tab) }}
            </span>
        </Link>
    </div>
</template>
