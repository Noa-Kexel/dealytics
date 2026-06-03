<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('/', '/search')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('favorites', 'Favorites')->name('favorites');
    Route::inertia('game-dashboard', 'GameDashboard')->name('game-dashboard');
});

Route::inertia('search', 'Search')->name('search');
Route::get('game/{id}', [GameController::class, 'show'])->name('game.show');

require __DIR__.'/settings.php';
