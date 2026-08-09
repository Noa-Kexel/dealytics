<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: editProfile(),
    },
    {
        title: 'Sécurité',
        href: editSecurity(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
        <Heading
            title="Paramètres"
            description="Gérez votre profil et les paramètres de votre compte"
        />

        <div class="flex flex-col gap-8 sm:gap-10">
            <aside class="flex justify-center">
                <nav
                    class="inline-flex items-center gap-1 rounded-xl border border-border/60 bg-secondary/30 p-1"
                    aria-label="Paramètres"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        size="sm"
                        :class="[
                            'rounded-lg px-4',
                            {
                                'bg-dealytics-purple/15 text-dealytics-purple hover:bg-dealytics-purple/20 hover:text-dealytics-purple':
                                    isCurrentOrParentUrl(item.href),
                            },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="opacity-60" />

            <section class="mx-auto w-full max-w-xl space-y-12">
                <slot />
            </section>
        </div>
    </div>
</template>
