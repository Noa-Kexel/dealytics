<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Users, Bell } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface AdminTab {
    label: string;
    href: string;
    icon: LucideIcon;
}

const tabs: AdminTab[] = [
    { label: 'Utilisateurs', href: '/admin/users', icon: Users },
    { label: 'Test notifications', href: '/admin/notifications', icon: Bell },
];

const page = usePage();

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
        </Link>
    </div>
</template>
