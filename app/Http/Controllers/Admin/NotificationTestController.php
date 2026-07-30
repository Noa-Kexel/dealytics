<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use App\Notifications\PriceAlertReached;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTestController extends Controller
{
    /**
     * Sample deal used both for the on-screen preview and the test send.
     */
    private const SAMPLE = [
        'game_id' => '96', // Cyberpunk 2077 — a real game id so the link works
        'title' => 'Cyberpunk 2077',
        'current_price' => 29.99,
        'target_price' => 35.00,
    ];

    public function index(): Response
    {
        return Inertia::render('admin/Notifications', [
            'sample' => [
                'gameId' => self::SAMPLE['game_id'],
                'title' => self::SAMPLE['title'],
                'currentPrice' => self::SAMPLE['current_price'],
                'targetPrice' => self::SAMPLE['target_price'],
            ],
        ]);
    }

    /**
     * Fire a real in-app price-alert notification at the current admin, so they
     * can see exactly what a triggered alert looks like (bell + toast).
     */
    public function send(Request $request): RedirectResponse
    {
        $alert = new PriceAlert;
        $alert->game_id = self::SAMPLE['game_id'];
        $alert->title = self::SAMPLE['title'];
        $alert->target_price = self::SAMPLE['target_price'];

        // notifyNow (not notify) sends synchronously despite ShouldQueue, and we
        // restrict to the database channel so no e-mail is sent for a test.
        $request->user()->notifyNow(
            new PriceAlertReached($alert, self::SAMPLE['current_price']),
            ['database'],
        );

        return back()->with('success', 'Notification de test envoyée.');
    }
}
