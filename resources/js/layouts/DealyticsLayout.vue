<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    CodeXml,
    Heart,
    Home,
    LayoutGrid,
    Twitter,
    Instagram,
    Link2,
    LogIn,
    Shield,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AlertToast from '@/components/AlertToast.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAlerts } from '@/composables/useAlerts';
import { getInitials } from '@/composables/useInitials';
import { useNotifications } from '@/composables/useNotifications';

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

    return (
        currentPath.value === path || currentPath.value.startsWith(path + '/')
    );
}

interface NavItem {
    title: string;
    href: string;
    icon: LucideIcon;
}

const isAdmin = computed(() =>
    ['admin', 'superadmin'].includes(
        (auth.value?.user?.role as string | undefined) ?? '',
    ),
);

const navItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Accueil', href: '/', icon: Home },
        { title: 'Favoris', href: '/favorites', icon: Heart },
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    ];

    if (isAdmin.value) {
        items.push({ title: 'Admin', href: '/admin/users', icon: Shield });
    }

    return items;
});

const serviceLinks = [
    { title: 'Accueil', href: '/' },
    { title: 'Favoris', href: '/favorites' },
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'FAQ', href: '/faq' },
    { title: 'Contact', href: '/contact' },
];

const legalLinks = [
    { title: 'Mentions légales', href: '/mentions-legales' },
    { title: 'Confidentialité', href: '/confidentialite' },
    { title: "Conditions d'utilisation", href: '/conditions-generales' },
];

const menuOpen = ref(false);
// Teleport côté client uniquement (évite les mismatches SSR Inertia).
const mounted = ref(false);

function closeMenu() {
    menuOpen.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeMenu();
    }
}

// Bloque le scroll et écoute Escape tant que le menu mobile est ouvert.
watch(menuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});

watch(() => page.url, closeMenu);

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});

const { loadAlerts, checkAlerts, startAlertPolling } = useAlerts();
const { loadNotifications } = useNotifications();

onMounted(async () => {
    mounted.value = true;

    await loadAlerts();
    await checkAlerts();
    startAlertPolling();

    if (auth.value?.user) {
        await loadNotifications();
    }
});
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-background">
        <header
            class="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 lg:px-6"
            >
                <Link href="/" class="flex items-center">
                    <AppLogo />
                </Link>
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
                <div class="flex items-center gap-3">
                    <NotificationBell v-if="auth?.user" />
                    <DropdownMenu v-if="auth?.user">
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-9 rounded-full"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
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
                    <Link
                        v-else
                        href="/login"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-dealytics-purple/50 bg-dealytics-purple/10 px-4 py-2 text-sm font-medium text-dealytics-purple transition-all duration-200 hover:border-dealytics-purple/70 hover:bg-dealytics-purple/20 hover:text-foreground active:scale-95"
                    >
                        <LogIn class="size-4" />
                        Connexion
                    </Link>
                    <button
                        type="button"
                        aria-label="Menu"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-menu"
                        class="group relative z-50 inline-flex size-9 items-center justify-center rounded-xl border transition-[color,background-color,border-color] duration-200 active:scale-95 md:hidden"
                        :class="
                            menuOpen
                                ? 'border-dealytics-purple/40 bg-dealytics-purple/10 text-dealytics-purple'
                                : 'border-border/60 bg-secondary/40 text-muted-foreground hover:bg-secondary hover:text-foreground'
                        "
                        @click="menuOpen = !menuOpen"
                    >
                        <span
                            class="relative block h-3.5 w-[18px]"
                            aria-hidden="true"
                        >
                            <span
                                class="absolute left-0 h-[2px] w-full rounded-full bg-current transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                                :class="
                                    menuOpen
                                        ? 'top-1/2 -translate-y-1/2 rotate-45'
                                        : 'top-0'
                                "
                            />
                            <span
                                class="absolute top-1/2 left-0 h-[2px] w-full -translate-y-1/2 rounded-full bg-current transition-all duration-200 ease-out"
                                :class="menuOpen ? 'scale-x-0 opacity-0' : ''"
                            />
                            <span
                                class="absolute left-0 h-[2px] w-full rounded-full bg-current transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                                :class="
                                    menuOpen
                                        ? 'top-1/2 -translate-y-1/2 -rotate-45'
                                        : 'bottom-0'
                                "
                            />
                        </span>
                    </button>
                    <Teleport v-if="mounted" to="body">
                        <Transition name="menu-fade">
                            <button
                                v-if="menuOpen"
                                type="button"
                                aria-label="Fermer le menu"
                                class="fixed top-16 right-0 bottom-0 left-0 z-40 cursor-default bg-background/70 backdrop-blur-sm md:hidden"
                                @click="closeMenu"
                            />
                        </Transition>

                        <Transition name="menu-drop">
                            <nav
                                v-if="menuOpen"
                                id="mobile-menu"
                                aria-label="Navigation principale"
                                class="fixed top-16 right-0 left-0 z-40 origin-top overflow-hidden rounded-b-2xl border-b border-border/60 bg-background/95 shadow-2xl shadow-black/50 backdrop-blur-xl md:hidden"
                            >
                                <div
                                    class="h-px bg-linear-to-r from-transparent via-dealytics-purple/60 to-transparent"
                                />
                                <div
                                    class="pointer-events-none absolute -top-10 right-6 size-40 rounded-full bg-dealytics-purple/20 blur-3xl"
                                />
                                <div
                                    class="relative mx-auto max-w-7xl px-4 py-4 lg:px-6"
                                >
                                    <ul class="flex flex-col gap-1">
                                        <li
                                            v-for="(item, index) in navItems"
                                            :key="item.title"
                                            class="animate-menu-item"
                                            :style="{
                                                animationDelay: `${100 + index * 55}ms`,
                                            }"
                                        >
                                            <Link
                                                :href="item.href"
                                                class="group/item flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-colors"
                                                :class="[
                                                    isActive(item.href)
                                                        ? 'bg-secondary text-foreground'
                                                        : 'text-muted-foreground hover:bg-secondary/50 hover:text-foreground',
                                                ]"
                                                @click="closeMenu"
                                            >
                                                <span
                                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                                    :class="[
                                                        isActive(item.href)
                                                            ? 'bg-dealytics-purple/15 text-dealytics-purple'
                                                            : 'bg-secondary/70 text-muted-foreground group-hover/item:text-foreground',
                                                    ]"
                                                >
                                                    <component
                                                        :is="item.icon"
                                                        class="size-[18px]"
                                                    />
                                                </span>
                                                <span class="flex-1">{{
                                                    item.title
                                                }}</span>
                                                <ChevronRight
                                                    class="size-4 transition-all duration-300 ease-out"
                                                    :class="[
                                                        isActive(item.href)
                                                            ? 'text-dealytics-purple'
                                                            : 'text-muted-foreground/30 group-hover/item:translate-x-0.5 group-hover/item:text-muted-foreground',
                                                    ]"
                                                />
                                            </Link>
                                        </li>
                                    </ul>
                                    <div
                                        class="mt-3 flex items-center justify-between border-t border-border/50 pt-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <a
                                                href="#"
                                                aria-label="X"
                                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                                            >
                                                <Twitter class="size-4" />
                                            </a>
                                            <a
                                                href="#"
                                                aria-label="Instagram"
                                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                                            >
                                                <Instagram class="size-4" />
                                            </a>
                                            <a
                                                href="#"
                                                aria-label="Lien"
                                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                                            >
                                                <Link2 class="size-4" />
                                            </a>
                                        </div>
                                        <span
                                            class="text-[11px] text-muted-foreground/60"
                                            >&copy;
                                            {{ new Date().getFullYear() }}
                                            Dealytics</span
                                        >
                                    </div>
                                </div>
                            </nav>
                        </Transition>
                    </Teleport>
                </div>
            </div>
            <div
                class="h-px bg-linear-to-r from-transparent via-dealytics-purple/50 to-transparent"
            />
        </header>
        <main class="flex-1 pb-8">
            <slot />
        </main>
        <footer
            class="border-t border-border/50 bg-background/80 backdrop-blur-xl"
        >
            <div class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
                <div class="grid gap-8 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="flex items-center text-base">
                            <AppLogoIcon class="h-[1cap] w-auto shrink-0" />
                            <span
                                class="text-gradient-purple -ml-0.5 font-heading text-base font-bold"
                                >EALYTICS</span
                            >
                        </div>
                        <p
                            class="mt-3 max-w-xs text-xs leading-relaxed text-muted-foreground"
                        >
                            La meilleure plateforme pour suivre les prix des
                            jeux et trouver les offres exceptionnelles.
                        </p>
                        <div class="mt-4 flex items-center gap-3">
                            <a
                                href="#"
                                aria-label="X"
                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                            >
                                <Twitter class="size-4" />
                            </a>
                            <a
                                href="#"
                                aria-label="Instagram"
                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                            >
                                <Instagram class="size-4" />
                            </a>
                            <a
                                href="#"
                                aria-label="Lien"
                                class="flex size-8 items-center justify-center rounded-lg bg-secondary/60 text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                            >
                                <Link2 class="size-4" />
                            </a>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-dealytics-purple">
                            Navigation
                        </h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="link in serviceLinks" :key="link.href">
                                <Link
                                    :href="link.href"
                                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {{ link.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-dealytics-purple">
                            Légal
                        </h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="link in legalLinks" :key="link.href">
                                <Link
                                    :href="link.href"
                                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {{ link.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <div
                    class="mt-10 flex flex-col items-center gap-2 border-t border-border/50 pt-6 text-[11px] text-muted-foreground/60 sm:flex-row sm:justify-between"
                >
                    <p>
                        &copy; {{ new Date().getFullYear() }} Dealytics, tous
                        droits réservés
                    </p>
                    <a
                        href="https://www.linkedin.com/in/noa-kexel-b5942b2a0/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group inline-flex items-center gap-1.5 text-dealytics-purple transition-colors duration-300 hover:text-dealytics-pink"
                    >
                        <CodeXml
                            class="size-3.5 shrink-0 text-inherit transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-12 motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-hover:rotate-0"
                            aria-hidden="true"
                        />
                        <span
                            class="font-bold text-inherit transition-transform duration-300 ease-out group-hover:translate-x-0.5 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0"
                            >Noa</span
                        >
                    </a>
                </div>
            </div>
            <div
                class="h-px bg-linear-to-r from-transparent via-dealytics-purple/30 to-transparent"
            />
        </footer>

        <AlertToast />
    </div>
</template>
