<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Connexion',
        description: 'Entrez vos identifiants pour accéder à votre compte',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Connexion" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 p-3 text-center text-sm font-medium text-green-400"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email" class="text-sm text-foreground/80">Adresse email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="votre@email.com"
                    class="border-border/50 bg-secondary/50 placeholder:text-muted-foreground/50 focus:border-dealytics-purple/50 focus:ring-dealytics-purple/20"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-sm text-foreground/80">Mot de passe</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs text-dealytics-purple hover:text-dealytics-purple/80"
                        :tabindex="5"
                    >
                        Mot de passe oublié ?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Votre mot de passe"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3 text-sm text-muted-foreground">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Se souvenir de moi</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <LogIn v-else class="size-4" />
                Se connecter
            </Button>
        </div>

        <div
            v-if="canRegister"
            class="text-center text-sm text-muted-foreground"
        >
            Pas encore de compte ?
            <TextLink
                :href="register()"
                :tabindex="5"
                class="text-dealytics-cyan hover:text-dealytics-cyan/80"
            >
                Créer un compte
            </TextLink>
        </div>
    </Form>
</template>
