<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le modèle User implémente désormais MustVerifyEmail : les routes protégées
     * par le middleware `verified` deviennent réellement inaccessibles tant que
     * l'adresse n'est pas confirmée.
     *
     * Les comptes créés avant cette bascule n'ont jamais reçu de lien de
     * vérification — les laisser en l'état les enfermerait hors de leur propre
     * compte. Ils sont donc considérés comme vérifiés.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Volontairement vide : une fois la migration passée, plus rien ne
        // distingue les comptes marqués ici de ceux qui ont réellement confirmé
        // leur adresse depuis. Les repasser à NULL invaliderait les deux.
    }
};
