<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class GameController extends Controller
{
    public function show(string $id)
    {
        return Inertia::render('Game/Show', [
            'gameId' => $id,
        ]);
    }
}
