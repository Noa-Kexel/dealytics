<?php

namespace App\Support;

class GameDescriptionFormatter
{
    /**
     * Sanitize Steam (or similar) HTML while preserving readable structure.
     */
    public static function fromHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = preg_replace('/<img[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<video[^>]*>.*?<\/video>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;

        $allowed = '<p><br><strong><b><em><i><ul><ol><li><h2><h3>';
        $html = strip_tags($html, $allowed);

        $html = preg_replace('/<p>\s*<\/p>/', '', $html) ?? $html;
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;

        return trim($html);
    }

    /**
     * Turn plain-text descriptions (RAWG) into simple HTML paragraphs.
     */
    public static function fromPlainText(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\R{2,}/', $text) ?: [];

        $html = collect($paragraphs)
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter()
            ->map(function (string $paragraph): string {
                $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $escaped = nl2br($escaped, false);

                return "<p>{$escaped}</p>";
            })
            ->implode('');

        return $html;
    }
}
