<?php
namespace App\Http\Controllers;

use App\Models\PreGame;
use Illuminate\Http\Request;

class PreGameController extends Controller
{
    // List all pre-games
    public function index()
    {
        // Hide full pregames from the public list
        $pregames = \App\Models\PreGame::orderBy('start_time')->get()->filter(function ($pregame) {
            return !$pregame->isFull();
        })->values();
        return view('pregames.index', compact('pregames'));
    }

    // Show details for a specific pre-game
    public function show(PreGame $pregame)
    {
        return view('pregames.show', compact('pregame'));
    }
}
