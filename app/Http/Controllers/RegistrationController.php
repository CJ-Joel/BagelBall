<?php
namespace App\Http\Controllers;


use App\Models\PreGame;
use App\Models\Registration;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

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
            return redirect()->route('pregames.signup', $pregame)->with('error', 'This pre-game is full.');
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

        // Stripe Checkout integration
        Stripe::setApiKey(config('services.stripe.secret'));
        $price = $pregame->price ?? 0;
        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $pregame->name,
                        'description' => $pregame->description,
                    ],
                    'unit_amount' => (int)($price * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $data['email'],
            'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('pregames.signup', $pregame),
            'metadata' => [
                'registration_id' => $registration->id,
                'pregame_id' => $pregame->id,
            ],
        ]);

        return redirect($session->url);
    }
}
