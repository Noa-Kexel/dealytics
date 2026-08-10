<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        icon: Component;
        label: string;
        value: string | number;
        hint?: string;
        iconClass?: string;
        iconBgClass?: string;
        valueClass?: string;
        /** Lien Inertia (navigation). */
        href?: string;
        /** Clic local (filtre / scroll) — rend le widget focusable. */
        clickable?: boolean;
        /** Surbrille le widget quand le filtre associé est actif. */
        active?: boolean;
    }>(),
    {
        iconClass: 'text-foreground',
        iconBgClass: 'bg-secondary',
        valueClass: 'text-foreground',
        clickable: false,
        active: false,
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const isInteractive = computed(() => Boolean(props.href) || props.clickable);

const rootTag = computed(() => {
    if (props.href) {
        return Link;
    }

    if (props.clickable) {
        return 'button';
    }

    return 'div';
});

const rootClass = computed(() => [
    'border-gradient relative block w-full rounded-xl p-4 text-left transition-all duration-200',
    isInteractive.value &&
        'group cursor-pointer ring-1 ring-dealytics-cyan/35 hover:-translate-y-0.5 hover:bg-dealytics-cyan/5 hover:ring-dealytics-cyan/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-dealytics-cyan/70',
    props.active && 'bg-dealytics-cyan/10 ring-dealytics-cyan/70',
]);

function onClick(event: MouseEvent) {
    if (props.clickable && !props.href) {
        emit('click', event);
    }
}
</script>

<template>
    <component
        :is="rootTag"
        :href="href"
        :type="clickable && !href ? 'button' : undefined"
        :class="rootClass"
        @click="onClick"
    >
        <div class="mb-2 flex items-start justify-between gap-2">
            <div
                class="flex size-8 items-center justify-center rounded-lg"
                :class="iconBgClass"
            >
                <component :is="icon" class="size-4" :class="iconClass" />
            </div>
            <ChevronRight
                v-if="isInteractive"
                class="size-4 shrink-0 text-dealytics-cyan transition-transform duration-200"
                :class="active ? 'translate-x-0.5' : 'opacity-80 group-hover:translate-x-0.5'"
            />
        </div>
        <div class="text-2xl font-bold" :class="valueClass">{{ value }}</div>
        <div class="text-xs text-muted-foreground">{{ label }}</div>
        <div
            v-if="hint"
            class="mt-1 text-[10px]"
            :class="isInteractive ? 'font-medium text-dealytics-cyan' : 'text-muted-foreground/70'"
        >
            {{ hint }}
        </div>
    </component>
</template>
