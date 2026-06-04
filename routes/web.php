<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Home')->name('home');
Route::redirect('/search', '/');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('favorites', 'Favorites')->name('favorites');
    Route::inertia('dashboard', 'GameDashboard')->name('dashboard');
});
Route::get('game/{id}', [GameController::class, 'show'])->name('game.show');

require __DIR__.'/settings.php';
