<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Mot de passe oublié',
        description: 'Entrez votre email pour recevoir un lien de réinitialisation',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 p-3 text-center text-sm font-medium text-green-400"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email" class="text-sm text-foreground/80">Adresse email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="votre@email.com"
                    class="border-border/50 bg-secondary/50 placeholder:text-muted-foreground/50 focus:border-dealytics-purple/50 focus:ring-dealytics-purple/20"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6">
                <Button
                    class="w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    <Mail v-else class="size-4" />
                    Envoyer le lien de réinitialisation
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            <span>Retour à la </span>
            <TextLink :href="login()" class="text-dealytics-cyan hover:text-dealytics-cyan/80">connexion</TextLink>
        </div>
    </div>
</template>
