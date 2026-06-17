<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('game_id');
            $table->decimal('price', 8, 2);
            $table->unsignedSmallInteger('discount')->default(0);
            $table->date('captured_on');
            $table->timestamps();

            // One snapshot per game per day.
            $table->unique(['game_id', 'captured_on']);
            $table->index('game_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
