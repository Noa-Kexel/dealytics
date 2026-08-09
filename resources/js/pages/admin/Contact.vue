<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    Inbox,
    Mail,
    MailOpen,
    Reply,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AdminTabs from '@/components/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/directives/reveal';

interface ContactMessage {
    id: number;
    name: string;
    email: string;
    subject: string;
    message: string;
    is_read: boolean;
    is_member: boolean;
    created_at: string;
}

const props = defineProps<{
    messages: ContactMessage[];
    unreadCount: number;
}>();

const page = usePage();

// Filtre d'affichage
const filter = ref<'all' | 'unread'>('all');

const visibleMessages = computed(() =>
    filter.value === 'unread'
        ? props.messages.filter((m) => !m.is_read)
        : props.messages,
);

// Un message ouvert à la fois.
const openId = ref<number | null>(null);

function toggle(message: ContactMessage) {
    openId.value = openId.value === message.id ? null : message.id;
}

// Actions
const actionForm = useForm({});

function toggleRead(message: ContactMessage) {
    actionForm.patch(`/admin/contact/${message.id}/read`, {
        preserveScroll: true,
    });
}

function destroy(message: ContactMessage) {
    if (
        !window.confirm(
            `Supprimer définitivement le message de ${message.name} ?`,
        )
    ) {
        return;
    }

    actionForm.delete(`/admin/contact/${message.id}`, { preserveScroll: true });
}

function mailtoLink(message: ContactMessage): string {
    return `mailto:${message.email}?subject=${encodeURIComponent('Re : ' + message.subject)}`;
}

// Flash notice
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash as { success?: string; error?: string } | undefined,
    (flash) => {
        if (flash?.success) {
            notice.value = { type: 'success', text: flash.success };
        } else if (flash?.error) {
            notice.value = { type: 'error', text: flash.error };
        } else {
            return;
        }

        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => (notice.value = null), 4500);
    },
    { deep: true, immediate: true },
);

function formatDate(value: string): string {
    return new Date(value).toLocaleString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Demandes de contact" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <AdminTabs />
        <div class="mb-6 flex items-center gap-3">
            <div
                class="flex size-10 items-center justify-center rounded-xl bg-dealytics-pink/20"
            >
                <Inbox class="size-5 text-dealytics-pink" />
            </div>
            <div>
                <h1
                    class="font-heading text-2xl font-bold text-foreground md:text-3xl"
                >
                    Demandes de contact
                </h1>
                <p class="text-xs text-muted-foreground">
                    Les messages envoyés depuis le formulaire public
                </p>
            </div>
        </div>
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="notice"
                class="mb-4 flex items-center gap-2 rounded-xl border p-3 text-sm"
                :class="
                    notice.type === 'success'
                        ? 'border-dealytics-cyan/30 bg-dealytics-cyan/10 text-dealytics-cyan'
                        : 'border-red-500/30 bg-red-500/10 text-red-400'
                "
            >
                <Check
                    v-if="notice.type === 'success'"
                    class="size-4 shrink-0"
                />
                <TriangleAlert v-else class="size-4 shrink-0" />
                <span class="min-w-0 flex-1">{{ notice.text }}</span>
                <button
                    class="shrink-0 text-current/60 hover:text-current"
                    @click="notice = null"
                >
                    <X class="size-4" />
                </button>
            </div>
        </Transition>
        <div
            v-reveal="{ y: 16 }"
            class="mb-6 flex flex-wrap items-center justify-between gap-4"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="border-gradient rounded-xl p-4">
                    <div
                        class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20"
                    >
                        <Mail class="size-4 text-dealytics-purple" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">
                        {{ messages.length }}
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        Messages reçus
                    </div>
                </div>
                <div class="border-gradient rounded-xl p-4">
                    <div
                        class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-pink/20"
                    >
                        <Inbox class="size-4 text-dealytics-pink" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">
                        {{ unreadCount }}
                    </div>
                    <div class="text-[10px] text-muted-foreground">Non lus</div>
                </div>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        filter === 'all'
                            ? 'bg-dealytics-purple/15 text-dealytics-purple'
                            : 'bg-secondary/50 text-muted-foreground hover:text-foreground'
                    "
                    @click="filter = 'all'"
                >
                    Tous
                </button>
                <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        filter === 'unread'
                            ? 'bg-dealytics-purple/15 text-dealytics-purple'
                            : 'bg-secondary/50 text-muted-foreground hover:text-foreground'
                    "
                    @click="filter = 'unread'"
                >
                    Non lus
                </button>
            </div>
        </div>
        <div v-if="visibleMessages.length" class="space-y-2">
            <article
                v-for="message in visibleMessages"
                :key="message.id"
                class="border-gradient overflow-hidden rounded-xl"
                :class="!message.is_read ? 'ring-1 ring-dealytics-pink/25' : ''"
            >
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-5 py-4 text-left"
                    :aria-expanded="openId === message.id"
                    @click="toggle(message)"
                >
                    <span
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            message.is_read
                                ? 'bg-secondary/60 text-muted-foreground'
                                : 'bg-dealytics-pink/15 text-dealytics-pink'
                        "
                    >
                        <MailOpen v-if="message.is_read" class="size-4" />
                        <Mail v-else class="size-4" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span
                                class="text-sm"
                                :class="
                                    message.is_read
                                        ? 'font-medium text-foreground'
                                        : 'font-semibold text-foreground'
                                "
                            >
                                {{ message.subject }}
                            </span>
                            <span
                                v-if="!message.is_read"
                                class="rounded-full bg-dealytics-pink px-1.5 py-0.5 text-[10px] leading-none font-bold text-white"
                            >
                                Nouveau
                            </span>
                            <span
                                v-if="message.is_member"
                                class="rounded-full bg-dealytics-purple/15 px-2 py-0.5 text-[10px] font-medium text-dealytics-purple"
                            >
                                Membre
                            </span>
                        </span>
                        <span
                            class="mt-1 block truncate text-xs text-muted-foreground"
                        >
                            {{ message.name }} · {{ message.email }} ·
                            {{ formatDate(message.created_at) }}
                        </span>
                    </span>

                    <ChevronDown
                        class="mt-1 size-4 shrink-0 transition-transform duration-300"
                        :class="
                            openId === message.id
                                ? 'rotate-180 text-dealytics-purple'
                                : 'text-muted-foreground/50'
                        "
                    />
                </button>

                <div
                    v-if="openId === message.id"
                    class="border-t border-border/40 px-5 py-4"
                >
                    <p
                        class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ message.message }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            :href="mailtoLink(message)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 px-3 py-2 text-xs text-muted-foreground transition-colors hover:border-dealytics-purple/40 hover:text-foreground"
                        >
                            <Reply class="size-3.5" />
                            Répondre par e-mail
                        </a>
                        <Button
                            variant="outline"
                            class="gap-1.5 text-xs"
                            :disabled="actionForm.processing"
                            @click="toggleRead(message)"
                        >
                            <MailOpen
                                v-if="!message.is_read"
                                class="size-3.5"
                            />
                            <Mail v-else class="size-3.5" />
                            {{
                                message.is_read
                                    ? 'Marquer comme non lu'
                                    : 'Marquer comme lu'
                            }}
                        </Button>
                        <Button
                            variant="outline"
                            class="gap-1.5 border-red-500/30 text-xs text-red-400 hover:bg-red-500/10 hover:text-red-300"
                            :disabled="actionForm.processing"
                            @click="destroy(message)"
                        >
                            <Trash2 class="size-3.5" />
                            Supprimer
                        </Button>
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="border-gradient flex flex-col items-center rounded-xl px-6 py-16 text-center"
        >
            <Inbox class="mb-3 size-10 text-muted-foreground/30" />
            <p class="text-sm font-medium text-foreground">
                {{
                    filter === 'unread'
                        ? 'Aucun message non lu'
                        : 'Aucune demande pour le moment'
                }}
            </p>
            <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                Les messages envoyés depuis la page
                <span class="text-dealytics-purple">/contact</span> apparaîtront
                ici.
            </p>
        </div>
    </div>
</template>
