<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        icon: Component;
        label: string;
        value: string | number;
        hint?: string;
        iconClass?: string;
        iconBgClass?: string;
        valueClass?: string;
    }>(),
    {
        iconClass: 'text-foreground',
        iconBgClass: 'bg-secondary',
        valueClass: 'text-foreground',
    },
);
</script>

<template>
    <div class="border-gradient rounded-xl p-4">
        <div
            class="mb-2 flex size-8 items-center justify-center rounded-lg"
            :class="iconBgClass"
        >
            <component :is="icon" class="size-4" :class="iconClass" />
        </div>
        <div class="text-2xl font-bold" :class="valueClass">{{ value }}</div>
        <div class="text-xs text-muted-foreground">{{ label }}</div>
        <div v-if="hint" class="text-[10px] text-muted-foreground/70">{{ hint }}</div>
    </div>
</template>
