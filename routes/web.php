<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Home')->name('home');
Route::redirect('/search', '/');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('favorites', 'Favorites')->name('favorites');
    Route::inertia('dashboard', 'GameDashboard')->name('dashboard');

    // API — Favoris
    Route::get('api/favorites', [FavoriteController::class, 'index']);
    Route::post('api/favorites', [FavoriteController::class, 'store']);
    Route::post('api/favorites/check', [FavoriteController::class, 'check']);
    Route::delete('api/favorites/{gameId}', [FavoriteController::class, 'destroy']);

    // API — Alertes prix
    Route::get('api/alerts', [PriceAlertController::class, 'index']);
    Route::post('api/alerts', [PriceAlertController::class, 'store']);
    Route::patch('api/alerts/{gameId}', [PriceAlertController::class, 'update']);
    Route::delete('api/alerts/{gameId}', [PriceAlertController::class, 'destroy']);

    // API — Achats
    Route::get('api/purchases', [PurchaseController::class, 'index']);
    Route::get('api/purchases/history', [PurchaseController::class, 'history']);
    Route::post('api/purchases', [PurchaseController::class, 'store']);
    Route::delete('api/purchases/{id}', [PurchaseController::class, 'destroy']);

    // API — Budget
    Route::get('api/budget', [BudgetController::class, 'show']);
    Route::put('api/budget', [BudgetController::class, 'update']);
});

Route::get('game/{id}', [GameController::class, 'show'])->name('game.show');
Route::get('api/rawg/{title}', [GameController::class, 'rawg'])->name('game.rawg');
Route::get('api/itad/{title}', [GameController::class, 'itad'])->name('game.itad');

require __DIR__.'/settings.php';
