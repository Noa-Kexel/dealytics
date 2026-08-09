<script setup lang="ts">
import { Check, Circle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        password?: string;
    }>(),
    {
        password: '',
    },
);

const rules = computed(() => {
    const value = props.password;

    return [
        {
            id: 'length',
            label: 'Au moins 12 caractères',
            met: value.length >= 12,
        },
        {
            id: 'lowercase',
            label: 'Une lettre minuscule',
            met: /[a-z]/.test(value),
        },
        {
            id: 'uppercase',
            label: 'Une lettre majuscule',
            met: /[A-Z]/.test(value),
        },
        {
            id: 'number',
            label: 'Un chiffre',
            met: /\d/.test(value),
        },
        {
            id: 'symbol',
            label: 'Un caractère spécial (!@#$…)',
            met: /[^A-Za-z0-9]/.test(value),
        },
    ];
});

const hasStartedTyping = computed(() => props.password.length > 0);
</script>

<template>
    <div
        class="rounded-md border border-border/50 bg-secondary/30 px-3 py-2.5"
        role="list"
        aria-label="Règles du mot de passe"
    >
        <p class="mb-2 text-xs font-medium text-foreground/80">
            Votre mot de passe doit contenir :
        </p>
        <ul class="space-y-1.5">
            <li
                v-for="rule in rules"
                :key="rule.id"
                role="listitem"
                class="flex items-center gap-2 text-xs"
                :class="
                    hasStartedTyping && rule.met
                        ? 'text-emerald-500'
                        : 'text-muted-foreground'
                "
            >
                <Check
                    v-if="hasStartedTyping && rule.met"
                    class="size-3.5 shrink-0"
                    aria-hidden="true"
                />
                <Circle
                    v-else
                    class="size-3.5 shrink-0 opacity-60"
                    aria-hidden="true"
                />
                <span>{{ rule.label }}</span>
            </li>
        </ul>
    </div>
</template>
