<?php
namespace App\Http\Controllers;


use App\Models\PreGame;
use App\Models\Registration;
use App\Models\EventbriteTicket;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class RegistrationController extends Controller
{
    // Show signup form
    public function create(PreGame $pregame)
    {
        \Log::info('CREATE: Session ID is ' . session()->getId());
        \Log::info('CREATE: CSRF Token is ' . csrf_token());

        if ($pregame->isFull()) {
            return redirect()->route('pregames.show', $pregame)->with('error', 'This pre-game is full.');
        }
        return view('pregames.signup', compact('pregame'));
    }

    // Validate Eventbrite order ID (AJAX endpoint)
    public function validateOrderId(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'pregame_id' => 'required|exists:pregames,id'
            ]);

            $orderId = $validated['order_id'];
            $pregameId = $validated['pregame_id'];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid request. Please check your order ID and try again.'
            ], 422);
        }

        // Check if order exists at all
        $anyTickets = EventbriteTicket::where('eventbrite_order_id', $orderId)->get();
        
        if ($anyTickets->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => 'Order not found. Please verify your Eventbrite order ID.'
            ]);
        }

        // Check if order has already been redeemed (assigned to a pregame)
        if ($anyTickets->whereNotNull('pregame_id')->isNotEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => 'This order has already been redeemed for another pre-game.'
            ]);
        }

        // Check if ticket has already been used for this specific pregame
        $usedTicket = Registration::where('eventbrite_order_id', $orderId)
            ->where('pregame_id', $pregameId)
            ->first();

        if ($usedTicket) {
            return response()->json([
                'valid' => false,
                'message' => 'This order has already been used for this pre-game.'
            ]);
        }

        // Return first ticket's info (primary attendee)
        $ticket = $anyTickets->first();
        
        // Check if registration is incomplete (Info Requested)
        $isIncomplete = $ticket->first_name === 'Info Requested' || 
                       $ticket->last_name === 'Info Requested' ||
                       empty($ticket->first_name) || 
                       empty($ticket->last_name) ||
                       empty($ticket->email);
        
        $response = [
            'valid' => true,
            'incomplete' => $isIncomplete,
            'data' => [
                'first_name' => $isIncomplete ? '' : $ticket->first_name,
                'last_name' => $isIncomplete ? '' : $ticket->last_name,
                'email' => $isIncomplete ? '' : $ticket->email,
                'ticket_count' => $anyTickets->count()
            ]
        ];

        if ($isIncomplete) {
            $response['message'] = 'Your registration on Eventbrite is incomplete. Please make sure you have provided all information requested. You may still signup for a pregame.';
        }

        // If there are 2 tickets, add friend info
        if ($anyTickets->count() === 2 && !$isIncomplete) {
            $friendTicket = $anyTickets->last();
            $response['data']['friend_name'] = $friendTicket->first_name . ' ' . $friendTicket->last_name;
            $response['data']['friend_email'] = $friendTicket->email;
        }
        
        return response()->json($response);
    }

    // Handle signup submission
    public function store(Request $request, PreGame $pregame)
    {
        \Log::info('STORE: Session ID is ' . session()->getId());
        \Log::info('STORE: Request Token is ' . $request->input('_token'));

        \Log::info('Store method called', [
            'pregame_id' => $pregame->id,
            'request_data' => $request->all()
        ]);
        
        if ($pregame->isFull()) {
            return redirect()->route('pregames.signup', $pregame)->with('error', 'This pre-game is full.');
        }
        $data = $request->validate([
            'eventbrite_order_id' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'has_friend' => 'boolean',
            'friend_name' => 'nullable|string|max:255',
            'friend_email' => 'nullable|email|max:255',
        ]);

        // Validate the order ID hasn't been used
        $existingReg = Registration::where('eventbrite_order_id', $data['eventbrite_order_id'])
            ->where('pregame_id', $pregame->id)
            ->first();

        if ($existingReg) {
            return redirect()->route('pregames.signup', $pregame)
                ->with('error', 'This Eventbrite order has already been used.');
        }

        // Verify ticket exists and hasn't been assigned yet
        $ticket = EventbriteTicket::where('eventbrite_order_id', $data['eventbrite_order_id'])
            ->whereNull('pregame_id')
            ->first();

        if (!$ticket) {
            return redirect()->route('pregames.signup', $pregame)
                ->with('error', 'Invalid Eventbrite order or ticket already used.');
        }

        // Assign the ticket to this pregame
        $ticket->update(['pregame_id' => $pregame->id]);

        $registration = $pregame->registrations()->create([
            'eventbrite_order_id' => $data['eventbrite_order_id'],
            'eventbrite_ticket_id' => $ticket->id,
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'friend_name' => $data['friend_name'] ?? null,
            'friend_email' => $data['friend_email'] ?? null,
            'payment_status' => 'pending',
        ]);

        // Stripe Checkout integration
        Stripe::setApiKey(config('services.stripe.secret'));
        $basePrice = $pregame->price ?? 0;
        
        // Calculate quantity based on whether friend is added
        $hasFriend = !empty($data['friend_name']) && !empty($data['friend_email']);
        $quantity = $hasFriend ? 2 : 1;
        
        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $pregame->name . ($hasFriend ? ' (x2)' : ''),
                        'description' => $pregame->description,
                    ],
                    'unit_amount' => (int)($basePrice * 100),
                ],
                'quantity' => $quantity,
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
