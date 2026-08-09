<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordRequirements from '@/components/PasswordRequirements.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Créer un compte',
        description: 'Rejoignez Dealytics et ne manquez plus aucune offre',
    },
});

const password = ref('');
</script>

<template>
    <Head title="Inscription" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @success="password = ''"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" class="text-sm text-foreground/80">Nom complet</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Votre nom"
                    class="border-border/50 bg-secondary/50 placeholder:text-muted-foreground/50 focus:border-dealytics-purple/50 focus:ring-dealytics-purple/20"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-sm text-foreground/80">Adresse email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="votre@email.com"
                    class="border-border/50 bg-secondary/50 placeholder:text-muted-foreground/50 focus:border-dealytics-purple/50 focus:ring-dealytics-purple/20"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-sm text-foreground/80">Mot de passe</Label>
                <PasswordInput
                    id="password"
                    v-model="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Créez un mot de passe"
                />
                <PasswordRequirements :password="password" />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" class="text-sm text-foreground/80">Confirmer le mot de passe</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirmez votre mot de passe"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                <UserPlus v-else class="size-4" />
                Créer mon compte
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Déjà un compte ?
            <TextLink
                :href="login()"
                class="text-dealytics-cyan hover:text-dealytics-cyan/80"
                :tabindex="6"
            >
                Se connecter
            </TextLink>
        </div>
    </Form>
</template>
