<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

type TurnstileTheme = 'light' | 'dark' | 'auto';

interface TurnstileApi {
    render: (
        element: HTMLElement,
        options: {
            sitekey: string;
            theme?: TurnstileTheme;
            callback?: (token: string) => void;
            'expired-callback'?: () => void;
            'error-callback'?: () => void;
        },
    ) => string;
    reset: (widgetId?: string) => void;
    remove: (widgetId?: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const props = withDefaults(
    defineProps<{
        siteKey: string;
        theme?: TurnstileTheme;
    }>(),
    {
        theme: 'auto',
    },
);

const model = defineModel<string>({ default: '' });

const container = ref<HTMLDivElement | null>(null);
let widgetId: string | undefined;
let scriptPromise: Promise<void> | null = null;

function loadScript(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            'script[data-cf-turnstile]',
        );

        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener(
                'error',
                () => reject(new Error('Turnstile script failed to load')),
                { once: true },
            );

            return;
        }

        const script = document.createElement('script');
        script.src =
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.dataset.cfTurnstile = '';
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Turnstile script failed to load'));
        document.head.appendChild(script);
    });

    return scriptPromise;
}

onMounted(async () => {
    if (!props.siteKey) {
        return;
    }

    try {
        await loadScript();
    } catch {
        return;
    }

    if (!container.value || !window.turnstile) {
        return;
    }

    widgetId = window.turnstile.render(container.value, {
        sitekey: props.siteKey,
        theme: props.theme,
        callback: (token: string) => {
            model.value = token;
        },
        'expired-callback': () => {
            model.value = '';
        },
        'error-callback': () => {
            model.value = '';
        },
    });
});

onBeforeUnmount(() => {
    if (widgetId !== undefined && window.turnstile) {
        window.turnstile.remove(widgetId);
    }
});

defineExpose({
    reset() {
        model.value = '';

        if (widgetId !== undefined && window.turnstile) {
            window.turnstile.reset(widgetId);
        }
    },
});
</script>

<template>
    <div ref="container" class="cf-turnstile" />
</template>
