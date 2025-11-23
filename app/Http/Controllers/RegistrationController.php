<?php
namespace App\Http\Controllers;

use App\Models\PreGame;
use App\Models\Registration;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    // Show signup form
    public function create(PreGame $pregame)
    {
        if ($pregame->isFull()) {
            return redirect()->route('pregames.show', $pregame)->with('error', 'This pre-game is full.');
        }
        return view('pregames.signup', compact('pregame'));
    }

    // Handle signup submission
    public function store(Request $request, PreGame $pregame)
    {
        if ($pregame->isFull()) {
            return redirect()->route('pregames.show', $pregame)->with('error', 'This pre-game is full.');
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'friend_name' => 'nullable|string|max:255',
            'friend_email' => 'nullable|email|max:255',
        ]);
        $registration = $pregame->registrations()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'friend_name' => $data['friend_name'] ?? null,
            'friend_email' => $data['friend_email'] ?? null,
            'payment_status' => 'pending',
        ]);
        // TODO: Redirect to Stripe Checkout or Eventbrite redemption
        return redirect()->route('checkout.success');
    }
}
