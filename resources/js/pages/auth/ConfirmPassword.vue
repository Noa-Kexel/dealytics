<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirmer le mot de passe',
        description: 'Ceci est une zone sécurisée. Veuillez confirmer votre mot de passe avant de continuer.',
    },
});
</script>

<template>
    <Head title="Confirmer le mot de passe" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div class="grid gap-2">
                <Label for="password" class="text-sm text-foreground/80">Mot de passe</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="Votre mot de passe"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                <ShieldCheck v-else class="size-4" />
                Confirmer
            </Button>
        </div>
    </Form>
</template>
