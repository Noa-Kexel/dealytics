<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Home, LayoutGrid, Menu, Zap } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';

const page = usePage();
const auth = computed(() => page.props.auth);

const currentPath = computed(() => {
    try {
        return new URL(page.url, window.location.origin).pathname;
    } catch {
        return page.url;
    }
});

function isActive(path: string): boolean {
    if (path === '/') {
        return currentPath.value === '/';
    }

    return currentPath.value === path || currentPath.value.startsWith(path + '/');
}

interface NavItem {
    title: string;
    href: string;
    icon: LucideIcon;
}

const navItems: NavItem[] = [
    { title: 'Accueil', href: '/', icon: Home },
    { title: 'Favoris', href: '/favorites', icon: Heart },
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
];
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-background">
        <!-- Header -->
        <header class="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 lg:px-6">
                <!-- Logo -->
                <Link href="/" class="flex items-center">
                    <AppLogo />
                </Link>

                <!-- Desktop Navigation -->
                <nav class="hidden items-center gap-1 md:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item.title"
                        :href="item.href"
                        class="relative flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all duration-200"
                        :class="[
                            isActive(item.href)
                                ? 'bg-secondary text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.title }}
                        <span
                            v-if="isActive(item.href)"
                            class="absolute bottom-0 left-1/2 size-1 -translate-x-1/2 translate-y-3 rounded-full bg-dealytics-purple"
                        />
                    </Link>
                </nav>

                <!-- Right side -->
                <div class="flex items-center gap-3">
                    <!-- PRO Button -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="hidden rounded-full border-dealytics-purple/50 text-dealytics-purple hover:bg-dealytics-purple/10 hover:text-dealytics-purple sm:flex"
                    >
                        <Zap class="mr-1 size-3.5 fill-dealytics-purple" />
                        PRO
                    </Button>

                    <!-- User menu -->
                    <DropdownMenu v-if="auth?.user">
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-9 rounded-full"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-full bg-dealytics-purple/20 text-xs font-semibold text-dealytics-purple"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Login link if not authenticated -->
                    <Link
                        v-else
                        href="/login"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        Connexion
                    </Link>

                    <!-- Mobile menu -->
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="size-9 md:hidden">
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-[280px] border-border/50 bg-background p-0">
                            <SheetTitle class="sr-only">Menu de navigation</SheetTitle>
                            <SheetHeader class="border-b border-border/50 p-4">
                                <div class="flex items-center text-base">
                                    <AppLogoIcon class="h-[1cap] w-auto shrink-0" />
                                    <span class="-ml-0.5 font-heading text-base font-bold text-gradient-purple">EALYTICS</span>
                                </div>
                            </SheetHeader>
                            <nav class="flex flex-col gap-1 p-3">
                                <Link
                                    v-for="item in navItems"
                                    :key="item.title"
                                    :href="item.href"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                                    :class="[
                                        isActive(item.href)
                                            ? 'bg-secondary text-foreground'
                                            : 'text-muted-foreground hover:bg-secondary/50 hover:text-foreground',
                                    ]"
                                >
                                    <component :is="item.icon" class="size-4" />
                                    {{ item.title }}
                                </Link>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <!-- Gradient line under header -->
            <div class="h-px bg-gradient-to-r from-transparent via-dealytics-purple/50 to-transparent" />
        </header>

        <!-- Main content -->
        <main class="flex-1 pb-8">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-border/50 bg-background/80 backdrop-blur-xl">
            <div class="mx-auto max-w-7xl px-4 py-8 lg:px-6">
                <div class="flex flex-col items-center gap-6 md:flex-row md:justify-between">
                    <div class="flex items-center text-sm">
                        <AppLogoIcon class="h-[1cap] w-auto shrink-0" />
                        <span class="-ml-0.5 font-heading text-sm font-bold text-gradient-purple">EALYTICS</span>
                    </div>

                    <nav class="flex items-center gap-6">
                        <Link
                            v-for="item in navItems"
                            :key="item.title"
                            :href="item.href"
                            class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>

                    <p class="text-[11px] text-muted-foreground/60">
                        &copy; {{ new Date().getFullYear() }} Dealytics &mdash; TFE Project
                    </p>
                </div>
            </div>
            <div class="h-px bg-gradient-to-r from-transparent via-dealytics-purple/30 to-transparent" />
        </footer>
    </div>
</template>
