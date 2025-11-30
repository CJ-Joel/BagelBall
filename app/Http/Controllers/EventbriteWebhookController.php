<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventbriteTicket;
use App\Models\PreGame;
use Illuminate\Support\Facades\Http;

class EventbriteWebhookController extends Controller
{
    // Handle Eventbrite order.placed webhook
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        // Log all webhook calls for debugging
        \Illuminate\Support\Facades\Log::info('Eventbrite webhook received', ['payload' => $payload]);

        // Check if this is an order.placed webhook with api_url
        if (isset($payload['api_url']) && isset($payload['config']['action'])) {
            $action = $payload['config']['action'];
            \Illuminate\Support\Facades\Log::info('Webhook action detected', ['action' => $action]);
            
            // Only process order.placed events
            if ($action === 'order.placed') {
                $apiUrl = $payload['api_url'];
                \Illuminate\Support\Facades\Log::info('Processing order.placed event', ['apiUrl' => $apiUrl]);
                
                try {
                    // Fetch the full order details from Eventbrite API
                    $token = config('services.eventbrite.token');
                    \Illuminate\Support\Facades\Log::info('Eventbrite token check', ['hasToken' => !empty($token)]);
                    
                    if (!$token) {
                        \Illuminate\Support\Facades\Log::error('Eventbrite token not configured');
                        return response()->json(['status' => 'error', 'message' => 'Token not configured'], 500);
                    }

                    \Illuminate\Support\Facades\Log::info('Making API request', ['url' => $apiUrl, 'method' => 'GET']);
                    
                    $response = Http::withToken($token)
                        ->withoutVerifying()
                        ->timeout(10)
                        ->retry(2, 100)
                        ->get($apiUrl . '?expand=attendees');

                    \Illuminate\Support\Facades\Log::info('API response received', ['status' => $response->status()]);

                    if ($response->successful()) {
                        $orderData = $response->json();
                        \Illuminate\Support\Facades\Log::info('Eventbrite order fetched', ['order' => $orderData]);
                        
                        // Process the order data
                        $this->processOrder($orderData);
                        
                        return response()->json(['status' => 'success', 'message' => 'Order processed']);
                    } else {
                        \Illuminate\Support\Facades\Log::error('Failed to fetch order from Eventbrite', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                            'apiUrl' => $apiUrl
                        ]);
                        return response()->json(['status' => 'error', 'message' => 'Failed to fetch order'], 500);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Exception fetching Eventbrite order', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'apiUrl' => $apiUrl
                    ]);
                    return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }

        // Legacy: Process order if attendees are directly in payload
        if (isset($payload['attendees']) && is_array($payload['attendees'])) {
            \Illuminate\Support\Facades\Log::info('Processing order from payload attendees');
            $this->processOrder($payload);
        }

        return response()->json(['status' => 'success']);
    }

    // Process order data and store tickets
    private function processOrder(array $orderData)
    {
        $eventId = $orderData['event_id'] ?? null;
        $orderId = $orderData['id'] ?? null;

        \Illuminate\Support\Facades\Log::info('processOrder called', [
            'event_id' => $eventId,
            'order_id' => $orderId,
            'has_attendees' => isset($orderData['attendees']) ? count($orderData['attendees']) : 0
        ]);

        if (!$eventId || !$orderId) {
            \Illuminate\Support\Facades\Log::warning('Order missing event_id or order id', ['order' => $orderData]);
            return;
        }

        // Find the matching PreGame
        $pregame = PreGame::where('eventbrite_event_id', $eventId)->first();
        
        if (!$pregame) {
            \Illuminate\Support\Facades\Log::warning('No PreGame found for event_id', ['event_id' => $eventId, 'available_event_ids' => PreGame::pluck('eventbrite_event_id')->toArray()]);
            return;
        }

        \Illuminate\Support\Facades\Log::info('PreGame found', ['pregame_id' => $pregame->id, 'pregame_name' => $pregame->name]);

        // Process attendees
        $attendees = $orderData['attendees'] ?? [];
        \Illuminate\Support\Facades\Log::info('Processing attendees', ['count' => count($attendees)]);
        
        foreach ($attendees as $attendee) {
            $eventbriteTicketId = $attendee['id'] ?? null;
            
            if (!$eventbriteTicketId) {
                \Illuminate\Support\Facades\Log::warning('Attendee missing ticket ID', ['attendee' => $attendee]);
                continue;
            }
            
            $profile = $attendee['profile'] ?? [];
            
            // Parse the datetime from Eventbrite format to MySQL format
            $redeemedAt = null;
            if (isset($attendee['created'])) {
                try {
                    $redeemedAt = \Carbon\Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to parse redeemed_at', ['error' => $e->getMessage()]);
                }
            }
            
            // Check if ticket already exists
            $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
            
            // Build update array, preserving existing redeemed_at if already set
            $updateData = [
                'pregame_id' => $pregame->id,
                'eventbrite_order_id' => $orderId,
                'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                'email' => $profile['email'] ?? $attendee['email'] ?? null,
            ];
            
            // Only set redeemed_at if it's not already set on existing record
            if (!$existingTicket || !$existingTicket->redeemed_at) {
                $updateData['redeemed_at'] = $redeemedAt;
            }
            
            try {
                EventbriteTicket::updateOrCreate(
                    [
                        'eventbrite_ticket_id' => $eventbriteTicketId,
                    ],
                    $updateData
                );
                
                \Illuminate\Support\Facades\Log::info('Ticket stored successfully', [
                    'ticket_id' => $eventbriteTicketId,
                    'order_id' => $orderId,
                    'pregame_id' => $pregame->id,
                    'first_name' => $updateData['first_name'],
                    'email' => $updateData['email']
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to store ticket', [
                    'ticket_id' => $eventbriteTicketId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    // Show the logged webhook payloads from Laravel logs
    public function showLog()
    {
        $logFile = storage_path('logs/laravel.log');
        $lines = [];
        
        if (file_exists($logFile)) {
            // Read the log file and filter for Eventbrite webhook entries
            $content = file_get_contents($logFile);
            $allLines = explode("\n", $content);
            
            // Get last 500 lines to avoid memory issues on large logs
            $recentLines = array_slice($allLines, -500);
            
            // Filter for Eventbrite webhook entries
            $eventbriteEntries = [];
            $currentEntry = '';
            
            foreach ($recentLines as $line) {
                if (strpos($line, 'Eventbrite webhook received') !== false) {
                    if ($currentEntry) {
                        $eventbriteEntries[] = $currentEntry;
                    }
                    $currentEntry = $line;
                } elseif ($currentEntry && (strpos($line, '[') === 0 || strpos($line, '{') !== false)) {
                    $currentEntry .= "\n" . $line;
                } elseif (empty(trim($line)) && $currentEntry) {
                    $eventbriteEntries[] = $currentEntry;
                    $currentEntry = '';
                }
            }
            
            if ($currentEntry) {
                $eventbriteEntries[] = $currentEntry;
            }
            
            $lines = array_reverse($eventbriteEntries); // Most recent first
        }
        
        return view('eventbrite.webhook_log', ['log' => implode("\n\n" . str_repeat('-', 80) . "\n\n", $lines)]);
    }

    // Temporary test method for webhook logging
    public function testLog()
    {
        \Illuminate\Support\Facades\Storage::append('eventbrite_webhook.log', 'Test log entry: ' . now());
        return response('Test log entry written.', 200);
    }

    // Send a sample order.placed webhook to test the handler
    public function sendTestWebhook()
    {
        // Sample payload based on Eventbrite's order.placed webhook structure
        $samplePayload = [
            'api_url' => 'https://www.eventbriteapi.com/v3/orders/1234567890/',
            'config' => [
                'action' => 'order.placed',
                'user_id' => '659292334583',
                'endpoint_url' => url('/webhooks/eventbrite'),
                'webhook_id' => '15644201'
            ],
            'event_id' => '123456789',
            'order_id' => '1234567890',
            'attendees' => [
                [
                    'id' => '9876543210',
                    'ticket_class_id' => '111222333',
                    'ticket_class_name' => 'General Admission',
                    'created' => now()->toIso8601String(),
                    'changed' => now()->toIso8601String(),
                    'profile' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'first_name' => 'John',
                        'last_name' => 'Doe'
                    ],
                    'barcodes' => [
                        [
                            'barcode' => 'TESTBARCODE123',
                            'status' => 'unused'
                        ]
                    ],
                    'checked_in' => false,
                    'cancelled' => false,
                    'refunded' => false,
                    'status' => 'Attending'
                ]
            ]
        ];

        // Call your webhook handler with the sample data
        $request = Request::create('/webhooks/eventbrite', 'POST', $samplePayload);
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $this->handle($request);

        return response()->json([
            'message' => 'Test webhook sent',
            'sample_payload' => $samplePayload,
            'handler_response' => $response->getData()
        ]);
    }

    // Lookup an order by ID using the Eventbrite API
    public function orderLookup(Request $request)
    {
        $orderId = $request->get('order_id');
        
        if (!$orderId) {
            return view('eventbrite.order_lookup');
        }

        try {
            $token = config('services.eventbrite.token');
            
            if (!$token) {
                return view('eventbrite.order_lookup', [
                    'error' => 'Eventbrite API token not configured. Please set EVENTBRITE_PRIVATE_TOKEN in your .env file.'
                ]);
            }

            // Fetch order from Eventbrite API
            // Note: withoutVerifying() disables SSL verification - only use if your server has SSL cert issues
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->get("https://www.eventbriteapi.com/v3/orders/{$orderId}/");

            if ($response->successful()) {
                return view('eventbrite.order_lookup', [
                    'orderData' => $response->json()
                ]);
            } else {
                return view('eventbrite.order_lookup', [
                    'error' => 'Failed to fetch order: ' . $response->status() . ' - ' . $response->body()
                ]);
            }
        } catch (\Exception $e) {
            return view('eventbrite.order_lookup', [
                'error' => 'Exception: ' . $e->getMessage()
            ]);
        }
    }

    // Sync orders from Eventbrite API (GET - show form)
    public function syncOrders(Request $request)
    {
        return view('eventbrite.sync', [
            'pregames' => PreGame::whereNotNull('eventbrite_event_id')->get()
        ]);
    }

    // Run sync via AJAX
    public function runSync(Request $request)
    {
        try {
            set_time_limit(300); // 5 minutes
            
            $pregameId = $request->input('pregame_id');
            
            if (!$pregameId) {
                return response()->json([
                    'success' => false,
                    'message' => 'PreGame ID is required'
                ]);
            }
            
            $token = config('services.eventbrite.token');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Eventbrite token not configured'
                ]);
            }
            
            $output = [];
            
            if ($pregameId === 'all') {
                $pregames = PreGame::whereNotNull('eventbrite_event_id')->get();
                
                if ($pregames->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No PreGames found with eventbrite_event_id set'
                    ]);
                }
                
                foreach ($pregames as $pregame) {
                    $output[] = "Syncing PreGame: {$pregame->name} (Event ID: {$pregame->eventbrite_event_id})";
                    $result = $this->syncEventOrders($pregame->eventbrite_event_id, $pregame->id, $token);
                    $output[] = $result;
                }
            } else {
                $pregame = PreGame::find($pregameId);
                if (!$pregame) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PreGame not found'
                    ]);
                }
                
                if (!$pregame->eventbrite_event_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PreGame does not have an Eventbrite Event ID set'
                    ]);
                }
                
                $output[] = "Syncing Event ID: {$pregame->eventbrite_event_id}";
                $result = $this->syncEventOrders($pregame->eventbrite_event_id, $pregame->id, $token);
                $output[] = $result;
            }
            
            return response()->json([
                'success' => true,
                'output' => implode("\n", $output),
                'message' => 'Sync completed successfully!'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sync error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    // Sync orders for a specific event
    private function syncEventOrders($eventId, $pregameId, $token)
    {
        $page = 1;
        $totalOrders = 0;
        $totalAttendees = 0;
        $output = [];

        while ($page <= 10) { // Limit to 10 pages max
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->timeout(30)
                ->get("https://www.eventbriteapi.com/v3/events/{$eventId}/orders/", [
                    'page' => $page,
                    'expand' => 'attendees'
                ]);

            if (!$response->successful()) {
                $output[] = "Failed to fetch orders (page {$page}): " . $response->status();
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            
            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                $orderId = $order['id'];
                $attendees = $order['attendees'] ?? [];
                
                $totalOrders++;

                foreach ($attendees as $attendee) {
                    $eventbriteTicketId = $attendee['id'] ?? null;
                    
                    if (!$eventbriteTicketId) {
                        continue;
                    }

                    $profile = $attendee['profile'] ?? [];
                    
                    // Parse the datetime from Eventbrite format to MySQL format
                    $redeemedAt = null;
                    if (isset($attendee['created'])) {
                        try {
                            $redeemedAt = \Carbon\Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            // If parsing fails, leave as null
                        }
                    }
                    
                    // Check if ticket already exists
                    $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
                    
                    // Build update array, preserving existing redeemed_at if already set
                    $updateData = [
                        'pregame_id' => null, // Will be assigned when user registers
                        'eventbrite_order_id' => $orderId,
                        'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                        'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                        'email' => $profile['email'] ?? $attendee['email'] ?? null,
                    ];
                    
                    // Only set redeemed_at if it's not already set on existing record
                    if (!$existingTicket || !$existingTicket->redeemed_at) {
                        $updateData['redeemed_at'] = $redeemedAt;
                    }
                    
                    EventbriteTicket::updateOrCreate(
                        ['eventbrite_ticket_id' => $eventbriteTicketId],
                        $updateData
                    );
                    
                    $totalAttendees++;
                }
            }

            // Check if there are more pages
            $pagination = $data['pagination'] ?? [];
            if (!($pagination['has_more_items'] ?? false)) {
                break;
            }
            
            $page++;
        }

        return "Summary: {$totalOrders} orders, {$totalAttendees} attendees synced";
    }
}
