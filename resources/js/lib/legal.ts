export interface LegalSectionMeta {
    id: string;
    title: string;
}

export interface LegalProps {
    editor: {
        name: string;
        status: string;
        address: string;
        country: string;
        email: string;
        company_number: string | null;
        publication_director: string;
    };
    host: {
        name: string;
        address: string;
        url: string | null;
        datacenter: string;
    };
    dpoEmail: string;
    updatedAt: string;
    dataSources: Array<{ name: string; usage: string; url: string }>;
    appName: string;
    appHost: string;
}

/**
 * Déclare les sections d'une page légale une seule fois : la liste alimente le
 * sommaire, et `section(id)` fournit les props (id + titre) du bloc concerné.
 * Les titres ne sont donc jamais dupliqués entre le sommaire et le contenu.
 */
export function defineLegalSections<T extends readonly LegalSectionMeta[]>(
    sections: T,
) {
    return {
        sections,
        section: (id: T[number]['id']): LegalSectionMeta =>
            sections.find((s) => s.id === id) as LegalSectionMeta,
    };
}

/** Formate une date ISO (`2026-07-30`) en date longue française. */
export function formatLegalDate(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat('fr-BE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}
