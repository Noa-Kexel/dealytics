<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatLegalDate } from '@/lib/legal';
import type { LegalSectionMeta } from '@/lib/legal';

const props = defineProps<{
    title: string;
    subtitle: string;
    updatedAt: string;
    sections: readonly LegalSectionMeta[];
}>();

const page = usePage();

const currentPath = computed(() => {
    try {
        return new URL(page.url, window.location.origin).pathname;
    } catch {
        return page.url;
    }
});

const relatedPages = [
    { href: '/mentions-legales', label: 'Mentions légales' },
    { href: '/confidentialite', label: 'Politique de confidentialité' },
    {
        href: '/conditions-generales',
        label: "Conditions générales d'utilisation",
    },
];

const otherPages = computed(() =>
    relatedPages.filter((link) => link.href !== currentPath.value),
);

const updatedLabel = computed(() => formatLegalDate(props.updatedAt));
</script>

<template>
    <Head :title="title" />

    <div class="animate-page-in mx-auto max-w-5xl px-4 py-10 lg:px-6">
        <!-- En-tête -->
        <header>
            <h1
                class="text-gradient-purple font-heading text-3xl font-bold md:text-4xl"
            >
                {{ title }}
            </h1>
            <p
                class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
            >
                {{ subtitle }}
            </p>
            <p class="mt-4 text-xs text-muted-foreground/70">
                Dernière mise à jour : {{ updatedLabel }}
            </p>
        </header>

        <div class="mt-10 grid gap-8 lg:grid-cols-[13rem_minmax(0,1fr)]">
            <!-- Sommaire -->
            <nav
                aria-label="Sommaire"
                class="lg:sticky lg:top-24 lg:self-start"
            >
                <p
                    class="text-[11px] font-semibold tracking-wider text-muted-foreground/70 uppercase"
                >
                    Sommaire
                </p>
                <ol class="mt-3 space-y-1">
                    <li v-for="(item, index) in sections" :key="item.id">
                        <a
                            :href="`#${item.id}`"
                            class="group flex gap-2 rounded-lg px-2 py-1.5 text-xs leading-snug text-muted-foreground transition-colors hover:bg-secondary/50 hover:text-foreground"
                        >
                            <span
                                class="text-dealytics-purple/70 tabular-nums group-hover:text-dealytics-purple"
                            >
                                {{ String(index + 1).padStart(2, '0') }}
                            </span>
                            {{ item.title }}
                        </a>
                    </li>
                </ol>
            </nav>

            <!-- Contenu -->
            <article class="border-gradient legal-prose rounded-2xl p-6 md:p-8">
                <div class="space-y-8">
                    <slot />
                </div>
            </article>
        </div>

        <!-- Renvois vers les autres pages légales -->
        <div class="mt-10 grid gap-3 sm:grid-cols-2">
            <Link
                v-for="link in otherPages"
                :key="link.href"
                :href="link.href"
                class="group flex items-center justify-between rounded-xl border border-border/60 bg-card/40 px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-dealytics-purple/40 hover:text-foreground"
            >
                {{ link.label }}
                <ArrowRight
                    class="size-4 text-muted-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:text-dealytics-purple"
                />
            </Link>
        </div>
    </div>
</template>
