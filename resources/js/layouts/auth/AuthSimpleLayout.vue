<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <!-- Background glow effects -->
        <div class="bg-glow-purple pointer-events-none absolute -top-40 -left-40 size-[500px] opacity-40" />
        <div class="bg-glow-cyan pointer-events-none absolute -bottom-40 -right-40 size-[500px] opacity-20" />
        <div class="bg-glow-purple pointer-events-none absolute top-1/2 left-1/2 size-[300px] -translate-x-1/2 -translate-y-1/2 opacity-10" />

        <div class="animate-page-in relative z-10 w-full max-w-md">
            <div class="flex flex-col gap-8">
                <!-- Logo + branding -->
                <div class="flex flex-col items-center gap-4">
                    <Link :href="home()" class="group flex items-center text-3xl transition-transform duration-300 hover:scale-105">
                        <AppLogoIcon class="h-[1cap] w-auto shrink-0" />
                        <span class="-ml-0.5 font-heading text-3xl font-bold tracking-wide text-gradient-purple">EALYTICS</span>
                    </Link>

                    <div class="space-y-2 text-center">
                        <h1 class="font-heading text-xl font-semibold text-foreground">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>

                <!-- Form card -->
                <div class="border-gradient rounded-2xl p-6 backdrop-blur-sm">
                    <slot />
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-8 text-center text-[11px] text-muted-foreground/40">
                &copy; {{ new Date().getFullYear() }} Dealytics &mdash; TFE Project
            </p>
        </div>
    </div>
</template>
