<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    protected $fillable = [
        'game_id',
        'title',
        'target_price',
        'current_price',
        'is_reached',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'current_price' => 'decimal:2',
            'is_reached' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
