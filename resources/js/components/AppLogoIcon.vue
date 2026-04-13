<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient id="bolt-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#A855F7" />
                <stop offset="100%" stop-color="#22D3EE" />
            </linearGradient>
        </defs>
        <rect width="32" height="32" rx="8" fill="url(#bolt-gradient)" />
        <path
            d="M17.5 7L11 17h4.5l-1 8L21 15h-4.5l1-8z"
            fill="white"
            stroke="white"
            stroke-width="0.5"
            stroke-linejoin="round"
        />
    </svg>
</template>
