<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Gamepad2,
    Home,
    LifeBuoy,
    Lock,
    SearchX,
    ServerCrash,
    ShieldAlert,
    Wrench,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/directives/reveal';

const props = defineProps<{
    status: number;
    /** Message renvoyé par le serveur, affiché seulement s'il est explicite. */
    message?: string;
}>();

interface ErrorCopy {
    title: string;
    description: string;
    icon: LucideIcon;
    /** Classe de couleur d'accent, reprise sur l'icône et le code. */
    accent: string;
}

const copies: Record<number, ErrorCopy> = {
    401: {
        title: 'Connexion requise',
        description:
            'Cette page est réservée aux membres. Connectez-vous pour retrouver vos favoris, vos alertes et votre budget.',
        icon: Lock,
        accent: 'text-dealytics-cyan',
    },
    403: {
        title: 'Accès refusé',
        description:
            "Vous n'avez pas les droits nécessaires pour consulter cette page. Si vous pensez qu'il s'agit d'une erreur, contactez-nous.",
        icon: ShieldAlert,
        accent: 'text-dealytics-pink',
    },
    404: {
        title: 'Page introuvable',
        description:
            "Cette page n'existe pas ou a été déplacée. Le jeu que vous cherchiez a peut-être aussi changé d'adresse.",
        icon: SearchX,
        accent: 'text-dealytics-purple',
    },
    419: {
        title: 'Session expirée',
        description:
            'Votre session a expiré par sécurité, faute d’activité. Rechargez la page et recommencez, rien n’a été perdu.',
        icon: Clock,
        accent: 'text-dealytics-cyan',
    },
    429: {
        title: 'Trop de requêtes',
        description:
            'Vous avez enchaîné un peu trop vite. Patientez quelques instants avant de réessayer.',
        icon: Clock,
        accent: 'text-dealytics-pink',
    },
    500: {
        title: 'Une erreur est survenue',
        description:
            "Le problème vient de chez nous, pas de vous. L'incident a été enregistré et sera examiné.",
        icon: ServerCrash,
        accent: 'text-dealytics-pink',
    },
    503: {
        title: 'Maintenance en cours',
        description:
            'Le site est momentanément indisponible, le temps d’une mise à jour. Revenez dans quelques minutes.',
        icon: Wrench,
        accent: 'text-dealytics-cyan',
    },
};

const fallback: ErrorCopy = {
    title: 'Quelque chose a mal tourné',
    description:
        "Une erreur inattendue s'est produite. Réessayez, et si le problème persiste, écrivez-nous.",
    icon: ServerCrash,
    accent: 'text-dealytics-purple',
};

const copy = computed<ErrorCopy>(() => copies[props.status] ?? fallback);

// Un message serveur générique n'apporte rien : on ne l'affiche que s'il est
// différent du texte standard de Laravel.
const extraMessage = computed(() => {
    const generic = ['Not Found', 'Forbidden', 'Server Error', 'Unauthorized'];

    return props.message && !generic.includes(props.message)
        ? props.message
        : '';
});

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`${status} — ${copy.title}`" />

    <div
        class="animate-page-in mx-auto flex min-h-[60vh] max-w-2xl flex-col items-center justify-center px-4 py-16 text-center lg:px-6"
    >
        <div v-reveal>
            <span
                class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-secondary/50"
                :class="copy.accent"
            >
                <component :is="copy.icon" class="size-8" />
            </span>

            <p
                class="text-gradient-hero mt-6 font-heading text-6xl leading-none font-bold md:text-7xl"
            >
                {{ status }}
            </p>

            <h1
                class="mt-4 font-heading text-2xl font-bold text-foreground md:text-3xl"
            >
                {{ copy.title }}
            </h1>

            <p
                class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-muted-foreground"
            >
                {{ copy.description }}
            </p>

            <p
                v-if="extraMessage"
                class="mx-auto mt-3 max-w-md rounded-lg bg-secondary/40 px-4 py-2 text-xs text-muted-foreground"
            >
                {{ extraMessage }}
            </p>

            <!-- Actions -->
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Link href="/">
                    <Button
                        class="gap-2 bg-dealytics-purple text-white hover:bg-dealytics-purple/90"
                    >
                        <Home class="size-4" />
                        Retour à l'accueil
                    </Button>
                </Link>
                <Button variant="outline" class="gap-2" @click="goBack">
                    <ArrowLeft class="size-4" />
                    Page précédente
                </Button>
            </div>

            <!-- Pistes utiles -->
            <div class="mt-10 grid gap-3 sm:grid-cols-3">
                <Link
                    href="/"
                    class="border-gradient rounded-xl p-4 text-left transition-transform hover:-translate-y-0.5"
                >
                    <Gamepad2 class="size-4 text-dealytics-purple" />
                    <span
                        class="mt-2 block text-xs font-semibold text-foreground"
                    >
                        Chercher un jeu
                    </span>
                    <span
                        class="mt-0.5 block text-[11px] text-muted-foreground"
                    >
                        Comparez les prix du catalogue
                    </span>
                </Link>
                <Link
                    href="/faq"
                    class="border-gradient rounded-xl p-4 text-left transition-transform hover:-translate-y-0.5"
                >
                    <LifeBuoy class="size-4 text-dealytics-cyan" />
                    <span
                        class="mt-2 block text-xs font-semibold text-foreground"
                    >
                        Consulter la FAQ
                    </span>
                    <span
                        class="mt-0.5 block text-[11px] text-muted-foreground"
                    >
                        Les questions les plus fréquentes
                    </span>
                </Link>
                <Link
                    href="/contact"
                    class="border-gradient rounded-xl p-4 text-left transition-transform hover:-translate-y-0.5"
                >
                    <LifeBuoy class="size-4 text-dealytics-pink" />
                    <span
                        class="mt-2 block text-xs font-semibold text-foreground"
                    >
                        Nous signaler le souci
                    </span>
                    <span
                        class="mt-0.5 block text-[11px] text-muted-foreground"
                    >
                        On vous répond par e-mail
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>
