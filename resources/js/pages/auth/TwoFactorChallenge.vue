<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Code de récupération',
            description: 'Entrez l\'un de vos codes de récupération d\'urgence pour accéder à votre compte.',
            buttonText: 'utiliser un code d\'authentification',
        };
    }

    return {
        title: 'Code d\'authentification',
        description: 'Entrez le code fourni par votre application d\'authentification.',
        buttonText: 'utiliser un code de récupération',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');
</script>

<template>
    <Head title="Authentification à deux facteurs" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div class="flex flex-col items-center justify-center space-y-3 text-center">
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                    :disabled="processing"
                >
                    <ShieldCheck class="size-4" />
                    Continuer
                </Button>
                <div class="text-center text-sm text-muted-foreground">
                    <span>ou vous pouvez </span>
                    <button
                        type="button"
                        class="text-dealytics-cyan underline underline-offset-4 transition-colors hover:text-dealytics-cyan/80"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Entrez le code de récupération"
                    :autofocus="showRecoveryInput"
                    required
                    class="border-border/50 bg-secondary/50 placeholder:text-muted-foreground/50 focus:border-dealytics-purple/50 focus:ring-dealytics-purple/20"
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    class="w-full gap-2 bg-dealytics-purple font-semibold text-white hover:bg-dealytics-deep-purple"
                    :disabled="processing"
                >
                    <ShieldCheck class="size-4" />
                    Continuer
                </Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>ou vous pouvez </span>
                    <button
                        type="button"
                        class="text-dealytics-cyan underline underline-offset-4 transition-colors hover:text-dealytics-cyan/80"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
