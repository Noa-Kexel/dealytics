import { createInertiaApp } from '@inertiajs/vue3';
import { useAlerts } from '@/composables/useAlerts';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import DealyticsLayout from '@/layouts/DealyticsLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Dealytics';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'Home':
            case name === 'Favorites':
            case name === 'GameDashboard':
            case name.startsWith('Game/'):
                return DealyticsLayout;
            default:
                return DealyticsLayout;
        }
    },
    progress: {
        color: '#A855F7',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Check price alerts on app load
const { checkAlerts } = useAlerts();
checkAlerts();
