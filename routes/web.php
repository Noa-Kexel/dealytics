<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\NotificationController;
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
    Route::post('api/alerts/check', [PriceAlertController::class, 'check']);
    Route::patch('api/alerts/{gameId}', [PriceAlertController::class, 'update']);
    Route::delete('api/alerts/{gameId}', [PriceAlertController::class, 'destroy']);

    // API — Notifications
    Route::get('api/notifications', [NotificationController::class, 'index']);
    Route::get('api/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('api/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('api/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

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
Route::get('api/games', [GameController::class, 'games'])->name('game.search');
Route::get('api/games/{id}/history', [GameController::class, 'history'])->whereNumber('id')->name('game.history');
Route::get('api/rawg/{title}', [GameController::class, 'rawg'])->name('game.rawg');
Route::get('api/nexarda/game/{id}', [GameController::class, 'nexardaById'])->whereNumber('id')->name('game.nexarda.id');
Route::get('api/nexarda/{title}', [GameController::class, 'nexarda'])->name('game.nexarda');

require __DIR__.'/settings.php';
