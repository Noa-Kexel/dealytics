<script setup lang="ts">
import { Gamepad2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string | null;
        alt: string;
        /** Hero / LCP images should load eagerly. */
        eager?: boolean;
    }>(),
    {
        eager: false,
    },
);

defineOptions({ inheritAttrs: false });

const failed = ref(false);

// Reset the error state if the source changes (e.g. list re-render).
watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <img
        v-if="src && !failed"
        :src="src"
        :alt="alt"
        :loading="eager ? 'eager' : 'lazy'"
        :fetchpriority="eager ? 'high' : undefined"
        decoding="async"
        v-bind="$attrs"
        @error="failed = true"
    />
    <div
        v-else
        class="flex items-center justify-center bg-gradient-to-br from-secondary to-background"
        v-bind="$attrs"
        role="img"
        :aria-label="alt"
    >
        <Gamepad2 class="size-8 text-muted-foreground/25" />
    </div>
</template>
