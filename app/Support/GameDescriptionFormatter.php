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
}
