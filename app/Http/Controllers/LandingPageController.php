<?php

namespace App\Http\Controllers;

class LandingPageController extends Controller
{
    /**
     * Show the landing page
     */
    public function index()
    {
        return view('landing');
    }
}
