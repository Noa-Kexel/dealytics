<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, Send, Check, BellRing, Info, Mail, ExternalLink } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AdminTabs from '@/components/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import { useNotifications } from '@/composables/useNotifications';

interface Sample {
    gameId: string;
    title: string;
    currentPrice: number;
    targetPrice: number;
}

interface MailTemplate {
    key: string;
    label: string;
}

const props = defineProps<{
    sample: Sample;
    templates: MailTemplate[];
    mailer: string;
}>();

const { loadNotifications } = useNotifications();
const sending = ref(false);
const sent = ref(false);

// ── Aperçu des e-mails ──────────────────────────────────────
const activeTemplate = ref(props.templates[0]?.key ?? '');
const previewUrl = computed(() => `/admin/notifications/preview/${activeTemplate.value}`);

const sendingEmail = ref(false);
const emailSent = ref(false);

function sendTestEmail() {
    sendingEmail.value = true;

    router.post(
        '/admin/notifications/test-email',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                emailSent.value = true;
                setTimeout(() => (emailSent.value = false), 4000);
            },
            onFinish: () => {
                sendingEmail.value = false;
            },
        },
    );
}

function sendTest() {
    sending.value = true;

    router.post(
        '/admin/notifications/test',
        {},
        {
            preserveScroll: true,
            onSuccess: async () => {
                // Same event a real triggered alert dispatches → shows the toast.
                window.dispatchEvent(
                    new CustomEvent('dealytics:alert-triggered', { detail: props.sample }),
                );
                // Refresh the bell to pick up the new database notification.
                window.dispatchEvent(new CustomEvent('dealytics:notifications-changed'));
                await loadNotifications();

                sent.value = true;
                setTimeout(() => (sent.value = false), 4000);
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Test des notifications" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <AdminTabs />

        <!-- Header -->
        <div class="mb-6 flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-cyan/20">
                <BellRing class="size-5 text-dealytics-cyan" />
            </div>
            <div>
                <h1 class="font-heading text-2xl font-bold text-foreground md:text-3xl">
                    Test des notifications
                </h1>
                <p class="text-xs text-muted-foreground">
                    Envoyez-vous un exemple d'alerte de prix et prévisualisez les e-mails
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Action -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-3 flex items-center gap-2">
                    <Bell class="size-4 text-dealytics-cyan" />
                    <h2 class="font-heading text-lg font-semibold">Envoyer un test</h2>
                </div>

                <p class="mb-4 text-sm text-muted-foreground">
                    Déclenche une vraie notification d'alerte de prix
                    <span class="font-medium text-foreground">à destination de votre compte</span> :
                    elle apparaît dans la cloche 🔔 et sous forme de toast, exactement comme lorsqu'un
                    prix cible est atteint.
                </p>

                <Button
                    class="w-full gap-2 bg-dealytics-cyan text-dealytics-dark hover:bg-dealytics-cyan/90"
                    :disabled="sending"
                    @click="sendTest"
                >
                    <Check v-if="sent" class="size-4" />
                    <Send v-else class="size-4" />
                    {{ sent ? 'Notification envoyée !' : 'Envoyer une notification de test' }}
                </Button>

                <div class="mt-4 flex items-start gap-2 rounded-lg bg-secondary/40 p-3 text-[11px] text-muted-foreground">
                    <Info class="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        Le test crée une notification <strong>in-app</strong> (cloche + toast). Un vrai
                        déclenchement envoie aussi un e-mail et, si autorisée, une notification
                        navigateur.
                    </span>
                </div>
            </div>

            <!-- Preview -->
            <div class="border-gradient rounded-xl p-6">
                <div class="mb-3 flex items-center gap-2">
                    <Info class="size-4 text-dealytics-purple" />
                    <h2 class="font-heading text-lg font-semibold">Aperçu</h2>
                </div>
                <p class="mb-4 text-sm text-muted-foreground">Voici le toast qui s'affichera :</p>

                <!-- Static replica of AlertToast -->
                <div class="w-full max-w-80 overflow-hidden rounded-xl border border-dealytics-cyan/30 bg-background/95 shadow-xl">
                    <div class="flex items-start gap-3 p-4">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-dealytics-cyan/15">
                            <Bell class="size-4 text-dealytics-cyan" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-dealytics-cyan">Prix cible atteint !</p>
                            <p class="mt-0.5 text-xs leading-snug text-foreground">
                                {{ sample.title }} est à {{ sample.currentPrice.toFixed(2) }}€
                                <span class="text-muted-foreground">
                                    (objectif : {{ sample.targetPrice.toFixed(2) }}€)
                                </span>
                            </p>
                            <span class="mt-2 inline-block text-xs font-medium text-dealytics-purple">
                                Voir l'offre →
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aperçu des e-mails -->
        <section class="border-gradient mt-6 rounded-xl p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Mail class="size-4 text-dealytics-purple" />
                    <h2 class="font-heading text-lg font-semibold">Aperçu des e-mails</h2>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        :href="previewUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 px-3 py-2 text-xs text-muted-foreground transition-colors hover:border-dealytics-purple/40 hover:text-foreground"
                    >
                        <ExternalLink class="size-3.5" />
                        Ouvrir dans un onglet
                    </a>
                    <Button
                        variant="outline"
                        class="gap-2 text-xs"
                        :disabled="sendingEmail"
                        @click="sendTestEmail"
                    >
                        <Check v-if="emailSent" class="size-3.5" />
                        <Send v-else class="size-3.5" />
                        {{ emailSent ? 'E-mail envoyé !' : "M'envoyer l'alerte par e-mail" }}
                    </Button>
                </div>
            </div>

            <!-- Sélecteur de gabarit -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="template in templates"
                    :key="template.key"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        activeTemplate === template.key
                            ? 'bg-dealytics-purple/15 text-dealytics-purple'
                            : 'bg-secondary/50 text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTemplate = template.key"
                >
                    {{ template.label }}
                </button>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-border/60">
                <iframe
                    :src="previewUrl"
                    title="Aperçu de l'e-mail"
                    class="h-[38rem] w-full bg-dealytics-dark"
                />
            </div>

            <p class="mt-3 flex items-start gap-2 text-[11px] text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                <span>
                    Les gabarits vivent dans <code>resources/views/emails</code> et sont rendus
                    ici avec des données d'exemple. Le driver de messagerie actif est
                    <strong>{{ mailer }}</strong
                    ><template v-if="mailer === 'log'">
                        : les e-mails ne partent pas et sont écrits dans
                        <code>storage/logs/laravel.log</code></template
                    >.
                </span>
            </p>
        </section>
    </div>
</template>
