<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceSnapshot extends Model
{
    protected $fillable = [
        'game_id',
        'price',
        'discount',
        'captured_on',
    ];

    protected $casts = [
        'captured_on' => 'date',
        'price' => 'decimal:2',
    ];

    /**
     * Record today's price for a game (idempotent — one row per game per day).
     */
    public static function record(string $gameId, float $price, int $discount = 0): void
    {
        $now = now();

        static::upsert(
            [
                [
                    'game_id' => $gameId,
                    'captured_on' => $now->toDateString(),
                    'price' => $price,
                    'discount' => $discount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['game_id', 'captured_on'],
            ['price', 'discount', 'updated_at'],
        );
    }
}
