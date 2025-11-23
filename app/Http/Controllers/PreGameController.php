<?php
namespace App\Http\Controllers;

use App\Models\PreGame;
use Illuminate\Http\Request;

class PreGameController extends Controller
{
    // List all pre-games
    public function index()
    {
        $pregames = \App\Models\PreGame::orderBy('start_time')->get();
        return view('pregames.index', compact('pregames'));
    }

    // Show details for a specific pre-game
    public function show(PreGame $pregame)
    {
        return view('pregames.show', compact('pregame'));
    }
}
