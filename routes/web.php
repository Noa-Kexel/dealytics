<?php

use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\NotificationTestController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SteamWishlistController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Home')->name('home');
Route::redirect('/search', '/');

Route::get('faq', FaqController::class)->name('faq');

// Formulaire de contact public. L'envoi est limité pour décourager le spam.
Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,60')
    ->name('contact.store');

// Pages légales (accessibles sans compte, référencées dans le pied de page et les e-mails).
Route::get('mentions-legales', [LegalController::class, 'notice'])->name('legal.notice');
Route::get('confidentialite', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('conditions-generales', [LegalController::class, 'terms'])->name('legal.terms');

// Admin panel — user management (admin + super admin only).
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('contact', [AdminContactController::class, 'index'])->name('contact.index');
        Route::patch('contact/{contactMessage}/read', [AdminContactController::class, 'toggleRead'])->name('contact.read');
        Route::delete('contact/{contactMessage}', [AdminContactController::class, 'destroy'])->name('contact.destroy');

        Route::get('notifications', [NotificationTestController::class, 'index'])->name('notifications.index');
        Route::post('notifications/test', [NotificationTestController::class, 'send'])->name('notifications.test');
        Route::post('notifications/test-email', [NotificationTestController::class, 'sendEmail'])->name('notifications.test-email');
        Route::get('notifications/preview/{template}', [NotificationTestController::class, 'preview'])->name('notifications.preview');
    });

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

    // API — Steam wishlist
    Route::get('api/steam/wishlist', [SteamWishlistController::class, 'show']);
    Route::post('api/steam/wishlist', [SteamWishlistController::class, 'store']);
    Route::delete('api/steam/wishlist', [SteamWishlistController::class, 'destroy']);
});

Route::get('api/stats', [StatsController::class, 'index'])->name('stats');

Route::get('game/{id}', [GameController::class, 'show'])->name('game.show');
Route::get('api/games', [GameController::class, 'games'])->name('game.search');
Route::get('api/games/{id}/history', [GameController::class, 'history'])->whereNumber('id')->name('game.history');
Route::get('api/steam/{title}', [GameController::class, 'steam'])->name('game.steam');
Route::get('api/nexarda/game/{id}', [GameController::class, 'nexardaById'])->whereNumber('id')->name('game.nexarda.id');
Route::get('api/nexarda/{title}', [GameController::class, 'nexarda'])->name('game.nexarda');

// Aperçu des pages d'erreur, uniquement en développement : en debug local les
// vraies erreurs affichent la trace de Laravel, ce qui empêche de les relire.
// Ces routes n'existent pas en production.
if (app()->isLocal()) {
    Route::get('_erreur/{status}', fn (int $status) => Inertia::render('Error', ['status' => $status]))
        ->whereNumber('status');

    Route::get('_erreur/statique/{status}', fn (int $status) => view("errors.{$status}"))
        ->whereIn('status', ['500', '503']);
}

require __DIR__.'/settings.php';
