<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les jaquettes de Nexarda passent par un proxy de redimensionnement qui
     * embarque l'URL d'origine encodée en paramètre : elles dépassent
     * systématiquement 255 caractères (≈ 270 en pratique).
     *
     * Le VARCHAR(255) par défaut passait inaperçu en développement — SQLite
     * n'applique pas les longueurs déclarées — mais MySQL, en mode strict,
     * rejetait l'insertion en production (SQLSTATE 22001) et l'ajout aux
     * favoris renvoyait une 500.
     *
     * 500 correspond à la règle de validation du contrôleur (`max:500`).
     */
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->string('thumb', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->string('thumb')->nullable()->change();
        });
    }
};
