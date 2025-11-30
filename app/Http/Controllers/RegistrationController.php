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

        // Look up ticket by order_id
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

        // Get first ticket's info (primary attendee)
        $ticket = $anyTickets->first();
        
        // Check if data has "Info Requested" or missing email
        $hasInfoRequested = $this->hasInfoRequested($ticket);
        $hasValidEmail = !empty($ticket->email);
        
        // If missing email or has "Info Requested", poll the API for fresh data
        if (!$hasValidEmail || $hasInfoRequested) {
            $this->fetchTicketsFromApi($orderId);
            // Refresh the ticket after API fetch
            $ticket = $ticket->fresh();
        }
        
        // Check again after API fetch
        $hasInfoRequested = $this->hasInfoRequested($ticket);
        
        // Return ticket info
        $response = [
            'valid' => true,
            'data' => [
                'first_name' => $this->isInfoRequested($ticket->first_name) ? '' : ($ticket->first_name ?? ''),
                'last_name' => $this->isInfoRequested($ticket->last_name) ? '' : ($ticket->last_name ?? ''),
                'email' => $this->isInfoRequested($ticket->email) ? '' : ($ticket->email ?? ''),
                'ticket_count' => $anyTickets->count()
            ]
        ];

        // Add notice if info is still missing
        if ($hasInfoRequested) {
            $response['message'] = 'Some of your information is missing on Eventbrite. Please fill in the fields below.';
        }

        // If there are 2 tickets, add friend info
        if ($anyTickets->count() === 2) {
            $friendTicket = $anyTickets->last();
            $response['data']['friend_name'] = ($this->isInfoRequested($friendTicket->first_name) ? '' : ($friendTicket->first_name ?? '')) . ' ' . ($this->isInfoRequested($friendTicket->last_name) ? '' : ($friendTicket->last_name ?? ''));
            $response['data']['friend_email'] = $this->isInfoRequested($friendTicket->email) ? '' : ($friendTicket->email ?? '');
        }
        
        return response()->json($response);
    }

    /**
     * Check if a field is "Info Requested" or empty
     */
    private function isInfoRequested($value): bool
    {
        return $value === 'Info Requested' || empty($value);
    }

    /**
     * Check if ticket has any "Info Requested" fields
     */
    private function hasInfoRequested($ticket): bool
    {
        return $this->isInfoRequested($ticket->first_name) || 
               $this->isInfoRequested($ticket->last_name) || 
               $this->isInfoRequested($ticket->email);
    }

    /**
     * Fetch tickets from Eventbrite API and update database
     */
    private function fetchTicketsFromApi(string $orderId): void
    {
        try {
            $token = config('services.eventbrite.token');
            if (!$token) {
                \Log::warning('Eventbrite token not configured');
                return;
            }

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->withoutVerifying()
                ->timeout(10)
                ->get("https://www.eventbriteapi.com/v3/orders/{$orderId}/?expand=attendees");

            if (!$response->successful()) {
                \Log::warning('Failed to fetch order from Eventbrite', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                ]);
                return;
            }

            $orderData = $response->json();
            $attendees = $orderData['attendees'] ?? [];

            if (empty($attendees)) {
                return;
            }

            // Update tickets from API response
            foreach ($attendees as $attendee) {
                $eventbriteTicketId = $attendee['id'] ?? null;
                
                if (!$eventbriteTicketId) {
                    continue;
                }
                
                $profile = $attendee['profile'] ?? [];
                
                // Parse order date
                $orderDate = null;
                if (isset($attendee['created'])) {
                    try {
                        $orderDate = \Carbon\Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse order_date', ['error' => $e->getMessage()]);
                    }
                }
                
                EventbriteTicket::updateOrCreate(
                    ['eventbrite_ticket_id' => $eventbriteTicketId],
                    [
                        'eventbrite_order_id' => $orderId,
                        'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                        'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                        'email' => $profile['email'] ?? $attendee['email'] ?? null,
                        'order_date' => $orderDate,
                        'pregame_id' => null,
                    ]
                );
            }

            \Log::info('Tickets updated from API', [
                'order_id' => $orderId,
                'attendee_count' => count($attendees),
            ]);

        } catch (\Exception $e) {
            \Log::error('Exception fetching tickets from Eventbrite', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Handle signup submission
    public function store(Request $request, PreGame $pregame)
    {
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

        // Create registration with payment_status = 'pending'
        // DO NOT assign ticket yet - only assign after successful payment
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
