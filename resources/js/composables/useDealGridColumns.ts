import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';

/** Classes Tailwind de la grille Accueil (doivent rester alignées avec les breakpoints ci-dessous). */
export const DEAL_GRID_CLASS = 'grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5';

/** Nombre de colonnes selon le viewport (2 → 3 → 5). */
export function useDealGridColumns() {
    const isXl = useMediaQuery('(min-width: 1280px)');
    const isLg = useMediaQuery('(min-width: 1024px)');

    const columns = computed(() => {
        if (isXl.value) {
            return 5;
        }

        if (isLg.value) {
            return 3;
        }

        return 2;
    });

    return { columns };
}

/** Tronque aux lignes pleines ; si `allowPartial`, garde le reste. */
export function takeCompleteRows<T>(items: T[], columns: number, allowPartial: boolean): T[] {
    if (allowPartial || columns <= 1 || items.length === 0) {
        return items;
    }

    const completeCount = Math.floor(items.length / columns) * columns;

    return completeCount === 0 ? [] : items.slice(0, completeCount);
}
