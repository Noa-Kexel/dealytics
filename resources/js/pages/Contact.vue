<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    HelpCircle,
    Mail,
    MessageSquare,
    Send,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { vReveal } from '@/directives/reveal';

const props = defineProps<{
    contactEmail: string;
    turnstileSiteKey: string | null;
    defaults: { name: string; email: string };
}>();

const page = usePage<{ flash?: { success?: string } }>();

const turnstileRef = ref<{ reset: () => void } | null>(null);

const form = useForm({
    name: props.defaults.name,
    email: props.defaults.email,
    subject: '',
    message: '',
    // Piège à robots : masqué, doit rester vide (validé côté serveur).
    website: '',
    turnstile_token: '',
});

const successMessage = computed(() => page.props.flash?.success ?? '');

const messageLength = computed(() => form.message.length);

function submit() {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'message', 'turnstile_token');
        },
        onError: () => {
            turnstileRef.value?.reset();
        },
    });
}

function writeAnother() {
    form.wasSuccessful = false;
}
</script>

<template>
    <Head title="Contact" />

    <div class="animate-page-in mx-auto max-w-4xl px-4 py-10 lg:px-6">
        <!-- En-tête -->
        <header class="text-center">
            <h1
                class="text-gradient-hero font-heading text-3xl font-bold md:text-4xl"
            >
                Une question ? Écrivez-nous
            </h1>
            <p
                class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground"
            >
                Un bug, une suggestion, un jeu introuvable ou une demande
                concernant vos données : ce formulaire arrive directement dans
                notre boîte.
            </p>
        </header>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <!-- Formulaire -->
            <section
                v-reveal
                class="border-gradient rounded-xl p-6 md:col-span-2"
            >
                <!-- Confirmation après envoi -->
                <div v-if="form.wasSuccessful" class="py-6 text-center">
                    <span
                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-dealytics-cyan/15 text-dealytics-cyan"
                    >
                        <CheckCircle2 class="size-6" />
                    </span>
                    <h2
                        class="mt-4 font-heading text-lg font-semibold text-foreground"
                    >
                        Message envoyé
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            successMessage ||
                            'Votre message est bien parti. Un accusé de réception vient de vous être envoyé par e-mail.'
                        }}
                    </p>
                    <Button
                        variant="outline"
                        class="mt-5 gap-2 text-xs"
                        @click="writeAnother"
                    >
                        <MessageSquare class="size-3.5" />
                        Écrire un autre message
                    </Button>
                </div>

                <form v-else class="space-y-5" @submit.prevent="submit">
                    <div class="mb-1 flex items-center gap-2">
                        <MessageSquare class="size-4 text-dealytics-purple" />
                        <h2 class="font-heading text-lg font-semibold">
                            Votre message
                        </h2>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">Nom</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                name="name"
                                required
                                autocomplete="name"
                                placeholder="Votre nom"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Adresse e-mail</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                required
                                autocomplete="email"
                                placeholder="vous@exemple.com"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="subject">Sujet</Label>
                        <Input
                            id="subject"
                            v-model="form.subject"
                            name="subject"
                            required
                            maxlength="150"
                            placeholder="Ex. : un prix affiché me semble incorrect"
                        />
                        <InputError :message="form.errors.subject" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-baseline justify-between">
                            <Label for="message">Message</Label>
                            <span class="text-[11px] text-muted-foreground">
                                {{ messageLength }} / 5000
                            </span>
                        </div>
                        <textarea
                            id="message"
                            v-model="form.message"
                            name="message"
                            required
                            rows="7"
                            maxlength="5000"
                            placeholder="Décrivez votre demande le plus précisément possible."
                            class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                        />
                        <InputError :message="form.errors.message" />
                    </div>

                    <!-- Champ piège : invisible pour un humain, rempli par les robots. -->
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Ne pas remplir</label>
                        <input
                            id="website"
                            v-model="form.website"
                            type="text"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                    <InputError :message="form.errors.website" />

                    <div v-if="turnstileSiteKey" class="grid gap-2">
                        <TurnstileWidget
                            ref="turnstileRef"
                            v-model="form.turnstile_token"
                            :site-key="turnstileSiteKey"
                            theme="auto"
                        />
                        <InputError :message="form.errors.turnstile_token" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <Button
                            type="submit"
                            class="gap-2 bg-dealytics-purple text-white hover:bg-dealytics-purple/90"
                            :disabled="form.processing"
                        >
                            <Send class="size-4" />
                            {{
                                form.processing
                                    ? 'Envoi en cours…'
                                    : 'Envoyer le message'
                            }}
                        </Button>
                        <p class="text-[11px] text-muted-foreground">
                            Vous recevrez une confirmation par e-mail.
                        </p>
                    </div>
                </form>
            </section>

            <!-- Informations -->
            <aside v-reveal class="space-y-4">
                <div class="border-gradient rounded-xl p-5">
                    <div class="mb-2 flex items-center gap-2">
                        <Mail class="size-4 text-dealytics-cyan" />
                        <h2 class="font-heading text-sm font-semibold">
                            Par e-mail
                        </h2>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Vous préférez votre propre messagerie ?
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-dealytics-purple hover:underline"
                        >
                            {{ contactEmail }}
                        </a>
                    </p>
                </div>

                <div class="border-gradient rounded-xl p-5">
                    <div class="mb-2 flex items-center gap-2">
                        <HelpCircle class="size-4 text-dealytics-purple" />
                        <h2 class="font-heading text-sm font-semibold">
                            Avant d'écrire
                        </h2>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        La
                        <Link
                            href="/faq"
                            class="text-dealytics-purple hover:underline"
                            >FAQ</Link
                        >
                        répond déjà aux questions les plus courantes : sources
                        des prix, alertes, gestion du compte.
                    </p>
                </div>

                <div class="border-gradient rounded-xl p-5">
                    <div class="mb-2 flex items-center gap-2">
                        <ShieldCheck class="size-4 text-dealytics-pink" />
                        <h2 class="font-heading text-sm font-semibold">
                            Vos données
                        </h2>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Votre message et votre adresse servent uniquement à vous
                        répondre. Voir la
                        <Link
                            href="/confidentialite"
                            class="text-dealytics-purple hover:underline"
                        >
                            politique de confidentialité </Link
                        >.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
