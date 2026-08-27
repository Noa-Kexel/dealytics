<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rapprochement de titres entre catalogues (Nexarda ↔ Steam).
 *
 * La recherche Steam renvoie toujours un « meilleur » résultat, même quand le
 * jeu n'est pas sur la boutique : « Grand Theft Auto VI » remonte « Grand Theft
 * Auto: Vice City – The Definitive Edition ». Accepter ce résultat les yeux
 * fermés remplissait la fiche avec la jaquette et la description d'un autre jeu.
 */
class GameTitleMatcher
{
    /**
     * Mots d'édition tolérés quand un catalogue est plus bavard que l'autre
     * (« Skyrim » ↔ « Skyrim Special Edition »). Tout autre mot en trop —
     * numéro de suite, sous-titre — invalide le rapprochement.
     */
    private const EDITION_WORDS = [
        'anniversary', 'bundle', 'classic', 'collection', 'complete', 'cut',
        'deluxe', 'definitive', 'digital', 'director', 'directors', 'edition',
        'enhanced', 'game', 'gold', 'goty', 'legendary', 'of', 'pack', 'pc',
        'premium', 'remaster', 'remastered', 'special', 'standard', 'the',
        'ultimate', 'year',
    ];

    /** Minuscules sans accent ni ponctuation : la base de comparaison. */
    public static function normalize(string $title): string
    {
        $clean = str_replace(['™', '®', '©'], '', $title);
        $clean = mb_strtolower(Str::ascii($clean));
        // L'apostrophe est supprimée plutôt que remplacée par une espace :
        // « Baldur's Gate » et « Baldurs Gate » doivent donner le même mot.
        $clean = str_replace(["'", '`'], '', $clean);
        $clean = preg_replace('/[^a-z0-9]+/', ' ', $clean) ?? '';

        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $title): array
    {
        $normalized = self::normalize($title);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /**
     * Vrai si les deux titres désignent le même jeu avec certitude.
     *
     * En cas de doute la réponse est non : une fiche sans enrichissement Steam
     * vaut mieux qu'une fiche remplie avec le mauvais jeu.
     */
    public static function matches(string $a, string $b): bool
    {
        $left = self::tokens($a);
        $right = self::tokens($b);

        if ($left === [] || $right === []) {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        [$short, $long] = count($left) <= count($right)
            ? [$left, $right]
            : [$right, $left];

        // Comparaison mot à mot : « vi » ne peut pas se faire passer pour « vice ».
        if (array_slice($long, 0, count($short)) !== $short) {
            return false;
        }

        foreach (array_slice($long, count($short)) as $extra) {
            if (! in_array($extra, self::EDITION_WORDS, true)) {
                return false;
            }
        }

        return true;
    }
}
