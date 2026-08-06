<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bell, ChevronDown, Gamepad2, Mail, UserCog } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import { vReveal } from '@/directives/reveal';

defineProps<{ contactEmail: string }>();

interface FaqItem {
    id: string;
    question: string;
    /** Réponse en HTML : autorise les liens internes et la mise en avant. */
    answer: string;
}

interface FaqCategory {
    title: string;
    icon: LucideIcon;
    items: FaqItem[];
}

const categories: FaqCategory[] = [
    {
        title: 'Le service',
        icon: Gamepad2,
        items: [
            {
                id: 'quest-ce-que',
                question: "Qu'est-ce que Dealytics ?",
                answer: "Dealytics est un comparateur et un outil de suivi de prix de jeux vidéo. Il rassemble les offres de plusieurs boutiques, affiche l'historique des prix d'un jeu et vous prévient quand celui-ci passe sous le prix que vous avez fixé. Le service est <strong>entièrement gratuit</strong>.",
            },
            {
                id: 'sources',
                question: "D'où viennent les prix affichés ?",
                answer: 'Les données proviennent de services spécialisés : <strong>Nexarda</strong> et <strong>IsThereAnyDeal</strong> pour les prix et leur historique, <strong>RAWG</strong> pour les fiches de jeux et les visuels, et <strong>Steam</strong> pour les informations de boutique. Le détail figure dans les <a href="/mentions-legales#sources">mentions légales</a>.',
            },
            {
                id: 'temps-reel',
                question: 'Les prix sont-ils mis à jour en temps réel ?',
                answer: "Non. Les prix sont rafraîchis régulièrement, mais un décalage de quelques minutes à quelques heures est possible selon la boutique. Ils sont donc <strong>indicatifs</strong> : vérifiez toujours le prix final sur le site du vendeur avant d'acheter.",
            },
            {
                id: 'commission',
                question:
                    'Dealytics vend-il des jeux ou touche-t-il une commission ?',
                answer: "Non. Dealytics ne vend rien, n'encaisse aucun paiement et n'utilise aucun lien d'affiliation rémunéré. Cliquer sur une offre vous renvoie simplement vers la boutique concernée.",
            },
            {
                id: 'boutiques',
                question: 'Les boutiques référencées sont-elles fiables ?',
                answer: 'Dealytics affiche les offres remontées par les comparateurs, sans les vérifier une par une. Certaines proviennent de revendeurs de clés : consultez la réputation du vendeur, les conditions de vente et la région de la clé avant tout achat. En cas de problème sur une commande, le litige se règle directement avec la boutique.',
            },
        ],
    },
    {
        title: 'Favoris et alertes',
        icon: Bell,
        items: [
            {
                id: 'favoris',
                question: 'Comment ajouter un jeu à mes favoris ?',
                answer: "Cliquez sur le cœur d'une carte de jeu ou sur sa fiche détaillée. Sans compte, vos favoris sont conservés dans votre navigateur ; avec un compte, ils sont synchronisés et vous les retrouvez sur tous vos appareils.",
            },
            {
                id: 'alerte-fonctionnement',
                question: 'Comment fonctionne une alerte de prix ?',
                answer: "Vous définissez un <strong>prix cible</strong> pour un jeu suivi. Dealytics compare régulièrement ce seuil au meilleur prix du moment et déclenche l'alerte dès que le prix passe en dessous.",
            },
            {
                id: 'alerte-notification',
                question:
                    'Comment suis-je prévenu quand une alerte se déclenche ?',
                answer: "De deux façons : une notification dans l'application (la cloche 🔔 et une bulle en bas de l'écran) et un <strong>e-mail</strong> récapitulant le prix atteint, avec un lien direct vers l'offre.",
            },
            {
                id: 'alerte-manquee',
                question: "Pourquoi n'ai-je pas reçu d'alerte ?",
                answer: "Plusieurs explications possibles : la promotion a été trop brève entre deux vérifications, le jeu n'était pas disponible chez les sources au moment du relevé, l'e-mail est arrivé dans les indésirables, ou l'alerte s'était déjà déclenchée — une alerte atteinte ne se redéclenche pas tant que vous ne l'avez pas supprimée puis recréée.",
            },
            {
                id: 'alerte-modifier',
                question: 'Puis-je modifier ou supprimer une alerte ?',
                answer: 'Le prix cible se définit depuis la fiche du jeu. Les alertes en cours, elles, se consultent et se suppriment depuis le <a href="/dashboard#price-alerts">dashboard</a>.',
            },
        ],
    },
    {
        title: 'Compte et données',
        icon: UserCog,
        items: [
            {
                id: 'compte-necessaire',
                question: "Ai-je besoin d'un compte ?",
                answer: "Non pour rechercher un jeu, comparer les prix et consulter l'historique. Un compte devient nécessaire pour <strong>synchroniser vos favoris</strong>, créer des alertes de prix, suivre votre budget et recevoir des notifications.",
            },
            {
                id: 'steam',
                question: 'Comment importer ma liste de souhaits Steam ?',
                answer: "Renseignez votre identifiant Steam dans les paramètres de votre compte. Votre profil et votre liste de souhaits doivent être <strong>publics</strong> pour que Steam nous les communique. L'identifiant peut être retiré à tout moment.",
            },
            {
                id: 'budget',
                question: 'À quoi sert le suivi de budget ?',
                answer: "Vous fixez un plafond de dépenses mensuel et enregistrez vos achats. Le tableau de bord affiche alors ce qu'il vous reste, vos économies réalisées par rapport aux prix d'origine, et l'évolution de vos dépenses.",
            },
            {
                id: 'suppression',
                question: 'Comment supprimer mon compte et mes données ?',
                answer: 'Depuis <strong>Paramètres → Profil</strong>. La suppression est définitive et efface immédiatement vos favoris, alertes, achats, budgets et notifications.',
            },
            {
                id: 'revente',
                question:
                    'Mes données sont-elles revendues ou utilisées à des fins publicitaires ?',
                answer: 'Non. Aucune donnée n\'est vendue ni cédée, le site n\'utilise aucun traceur publicitaire et les e-mails envoyés sont strictement transactionnels. Tout est détaillé dans la <a href="/confidentialite">politique de confidentialité</a>.',
            },
        ],
    },
];

// Une seule réponse ouverte à la fois : la première l'est au chargement.
const openId = ref<string | null>(categories[0].items[0].id);

function toggle(id: string) {
    openId.value = openId.value === id ? null : id;
}
</script>

<template>
    <Head title="FAQ" />

    <div class="animate-page-in mx-auto max-w-4xl px-4 py-10 lg:px-6">
        <!-- En-tête -->
        <header class="text-center">
            <h1
                class="text-gradient-hero font-heading text-3xl font-bold md:text-4xl"
            >
                Comment fonctionne Dealytics ?
            </h1>
            <p
                class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground"
            >
                Les prix, les alertes, votre compte : tout ce qu'il faut savoir
                avant de traquer votre prochaine bonne affaire.
            </p>
        </header>

        <!-- Catégories -->
        <div class="mt-10 space-y-8">
            <section
                v-for="category in categories"
                :key="category.title"
                v-reveal
            >
                <div class="mb-3 flex items-center gap-2">
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/15 text-dealytics-purple"
                    >
                        <component :is="category.icon" class="size-4" />
                    </span>
                    <h2
                        class="font-heading text-lg font-semibold text-foreground"
                    >
                        {{ category.title }}
                    </h2>
                </div>

                <ul class="space-y-2">
                    <li
                        v-for="item in category.items"
                        :key="item.id"
                        class="border-gradient overflow-hidden rounded-xl"
                    >
                        <h3>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-medium transition-colors"
                                :class="
                                    openId === item.id
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-expanded="openId === item.id"
                                :aria-controls="`faq-${item.id}`"
                                @click="toggle(item.id)"
                            >
                                {{ item.question }}
                                <ChevronDown
                                    class="size-4 shrink-0 transition-transform duration-300"
                                    :class="
                                        openId === item.id
                                            ? 'rotate-180 text-dealytics-purple'
                                            : 'text-muted-foreground/50'
                                    "
                                />
                            </button>
                        </h3>

                        <div
                            v-show="openId === item.id"
                            :id="`faq-${item.id}`"
                            role="region"
                            class="faq-answer px-5 pb-4 text-sm leading-relaxed text-muted-foreground"
                            v-html="item.answer"
                        />
                    </li>
                </ul>
            </section>
        </div>

        <!-- Contact -->
        <div
            class="border-gradient mt-10 flex flex-col items-center gap-3 rounded-2xl p-6 text-center"
        >
            <span
                class="flex size-10 items-center justify-center rounded-xl bg-dealytics-cyan/15 text-dealytics-cyan"
            >
                <Mail class="size-5" />
            </span>
            <p class="font-heading text-base font-semibold text-foreground">
                Vous n'avez pas trouvé votre réponse ?
            </p>
            <p class="max-w-md text-sm text-muted-foreground">
                Écrivez-nous : une donnée erronée, un bug ou une suggestion,
                tout retour est le bienvenu.
            </p>
            <a
                :href="`mailto:${contactEmail}`"
                class="inline-flex items-center gap-2 rounded-lg border border-dealytics-purple/50 bg-dealytics-purple/10 px-4 py-2 text-sm font-medium text-dealytics-purple transition-all duration-200 hover:border-dealytics-purple/70 hover:bg-dealytics-purple/20 hover:text-foreground active:scale-95"
            >
                <Mail class="size-4" />
                {{ contactEmail }}
            </a>
            <p class="mt-1 text-[11px] text-muted-foreground/70">
                Voir aussi les
                <Link
                    href="/mentions-legales"
                    class="text-dealytics-purple hover:underline"
                    >mentions légales</Link
                >
                et les
                <Link
                    href="/conditions-generales"
                    class="text-dealytics-purple hover:underline"
                    >conditions d'utilisation</Link
                >.
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Les réponses contiennent du HTML simple (liens, mise en gras). */
.faq-answer :deep(a) {
    color: var(--color-dealytics-purple);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.faq-answer :deep(a:hover) {
    color: var(--color-dealytics-pink);
}

.faq-answer :deep(strong) {
    color: var(--color-foreground);
    font-weight: 600;
}
</style>
