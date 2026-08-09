<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Informations légales
    |--------------------------------------------------------------------------
    |
    | Ces valeurs alimentent les pages légales (mentions légales, politique de
    | confidentialité, CGU) ainsi que le pied de page des e-mails. Elles sont
    | centralisées ici pour n'avoir qu'un seul endroit à modifier.
    |
    | Le `?:` garantit qu'une variable d'environnement vide retombe sur la
    | valeur par défaut plutôt que d'afficher un champ vide en production.
    |
    */

    'editor' => [
        // Personne physique ou morale qui édite le site.
        'name' => env('LEGAL_EDITOR_NAME') ?: 'Noa Kexel',
        'status' => env('LEGAL_EDITOR_STATUS') ?: 'Personne physique (projet étudiant, travail de fin d\'études)',
        'address' => env('LEGAL_EDITOR_ADDRESS') ?: 'Boncelles',
        'country' => env('LEGAL_EDITOR_COUNTRY') ?: 'Belgique',
        'email' => env('LEGAL_EDITOR_EMAIL') ?: 'noa.kexel@gmail.com',
        // Numéro d'entreprise / TVA — laisser vide si non applicable.
        'company_number' => env('LEGAL_EDITOR_COMPANY_NUMBER') ?: null,
        'publication_director' => env('LEGAL_PUBLICATION_DIRECTOR') ?: 'Noa Kexel',
    ],

    'host' => [
        'name' => env('LEGAL_HOST_NAME') ?: 'Hostinger International Ltd.',
        'address' => env('LEGAL_HOST_ADDRESS') ?: '61 Lordou Vironos Street, 6023 Larnaca, Chypre',
        'url' => env('LEGAL_HOST_URL') ?: 'https://www.hostinger.com',
        // Localisation effective des serveurs : détermine où les données sont stockées.
        'datacenter' => env('LEGAL_HOST_DATACENTER') ?: 'Francfort, Allemagne (Union européenne)',
    ],

    'dpo_email' => env('LEGAL_DPO_EMAIL') ?: (env('LEGAL_EDITOR_EMAIL') ?: 'noa.kexel@gmail.com'),

    // Date de dernière mise à jour affichée en tête des pages légales.
    'updated_at' => env('LEGAL_UPDATED_AT') ?: '2026-07-30',

    /*
    |--------------------------------------------------------------------------
    | Sources de données tierces
    |--------------------------------------------------------------------------
    |
    | Services externes interrogés par l'application. Ils sont listés dans les
    | mentions légales et la politique de confidentialité.
    |
    */

    'data_sources' => [
        [
            'name' => 'Nexarda',
            'usage' => 'Catalogue de jeux et comparaison des prix entre boutiques',
            'url' => 'https://www.nexarda.com',
        ],
        [
            'name' => 'IsThereAnyDeal',
            'usage' => 'Historique des prix et meilleures offres',
            'url' => 'https://isthereanydeal.com',
        ],
        [
            'name' => 'Steam (Valve)',
            'usage' => 'Fiches de jeux, visuels, notes, liste de souhaits publique et informations de boutique',
            'url' => 'https://store.steampowered.com',
        ],
    ],

];
