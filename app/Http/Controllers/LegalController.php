<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function notice(): Response
    {
        return Inertia::render('legal/Notice', $this->sharedProps());
    }

    public function privacy(): Response
    {
        return Inertia::render('legal/Privacy', $this->sharedProps());
    }

    public function terms(): Response
    {
        return Inertia::render('legal/Terms', $this->sharedProps());
    }

    /**
     * Identité de l'éditeur, hébergeur et sources de données : les trois pages
     * légales s'appuient sur les mêmes informations (config/legal.php).
     *
     * @return array<string, mixed>
     */
    private function sharedProps(): array
    {
        return [
            'legal' => [
                'editor' => config('legal.editor'),
                'host' => config('legal.host'),
                'dpoEmail' => config('legal.dpo_email'),
                'updatedAt' => config('legal.updated_at'),
                'dataSources' => config('legal.data_sources'),
                'appName' => config('app.name'),
                'appHost' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url'),
            ],
        ];
    }
}
