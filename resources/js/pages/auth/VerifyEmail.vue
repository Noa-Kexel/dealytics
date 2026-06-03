<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Vérifiez votre email',
        description: 'Cliquez sur le lien que nous venons de vous envoyer par email pour vérifier votre adresse.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Vérification email" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 p-3 text-center text-sm font-medium text-green-400"
    >
        Un nouveau lien de vérification a été envoyé à votre adresse email.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            class="w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
        >
            <Spinner v-if="processing" />
            <Mail v-else class="size-4" />
            Renvoyer l'email de vérification
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="mx-auto block text-sm text-muted-foreground hover:text-foreground"
        >
            Se déconnecter
        </TextLink>
    </Form>
</template>
