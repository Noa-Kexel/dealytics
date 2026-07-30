<script setup lang="ts">
/**
 * Un bloc d'une page légale : ancre pour le sommaire + titre de niveau 2.
 * Le contenu passé en slot est mis en forme par la classe `.legal-prose`
 * portée par LegalPage.
 */
defineProps<{
    id: string;
    title: string;
}>();
</script>

<template>
    <section
        :id="id"
        class="scroll-mt-24 border-t border-border/50 pt-8 first:border-0 first:pt-0"
    >
        <h2
            class="font-heading text-lg font-semibold text-foreground md:text-xl"
        >
            {{ title }}
        </h2>
        <div class="mt-3">
            <slot />
        </div>
    </section>
</template>
