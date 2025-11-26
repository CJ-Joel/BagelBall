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
            
            // Only process order.placed events
            if ($action === 'order.placed') {
                $apiUrl = $payload['api_url'];
                
                try {
                    // Fetch the full order details from Eventbrite API
                    $token = config('services.eventbrite.token');
                    
                    if (!$token) {
                        \Illuminate\Support\Facades\Log::error('Eventbrite token not configured');
                        return response()->json(['status' => 'error', 'message' => 'Token not configured'], 500);
                    }

                    $response = Http::withToken($token)
                        ->withoutVerifying()
                        ->get($apiUrl);

                    if ($response->successful()) {
                        $orderData = $response->json();
                        \Illuminate\Support\Facades\Log::info('Eventbrite order fetched', ['order' => $orderData]);
                        
                        // Process the order data
                        $this->processOrder($orderData);
                        
                        return response()->json(['status' => 'success', 'message' => 'Order processed']);
                    } else {
                        \Illuminate\Support\Facades\Log::error('Failed to fetch order from Eventbrite', [
                            'status' => $response->status(),
                            'body' => $response->body()
                        ]);
                        return response()->json(['status' => 'error', 'message' => 'Failed to fetch order'], 500);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Exception fetching Eventbrite order', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }

        // Legacy: Process order if attendees are directly in payload
        if (isset($payload['attendees']) && is_array($payload['attendees'])) {
            $this->processOrder($payload);
        }

        return response()->json(['status' => 'success']);
    }

    // Process order data and store tickets
    private function processOrder(array $orderData)
    {
        $eventId = $orderData['event_id'] ?? null;
        $orderId = $orderData['id'] ?? null;

        if (!$eventId || !$orderId) {
            \Illuminate\Support\Facades\Log::warning('Order missing event_id or order id', ['order' => $orderData]);
            return;
        }

        // Find the matching PreGame
        $pregame = PreGame::where('eventbrite_event_id', $eventId)->first();
        
        if (!$pregame) {
            \Illuminate\Support\Facades\Log::warning('No PreGame found for event_id', ['event_id' => $eventId]);
            return;
        }

        // Process attendees
        $attendees = $orderData['attendees'] ?? [];
        
        foreach ($attendees as $attendee) {
            $eventbriteTicketId = $attendee['id'] ?? null;
            
            if (!$eventbriteTicketId) {
                continue;
            }
            
            $profile = $attendee['profile'] ?? [];
            
            EventbriteTicket::updateOrCreate(
                [
                    'eventbrite_ticket_id' => $eventbriteTicketId,
                ],
                [
                    'pregame_id' => $pregame->id,
                    'eventbrite_order_id' => $orderId,
                    'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                    'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                    'email' => $profile['email'] ?? $attendee['email'] ?? null,
                    'redeemed_at' => $attendee['created'] ?? null,
                ]
            );
            
            \Illuminate\Support\Facades\Log::info('Ticket stored', [
                'ticket_id' => $eventbriteTicketId,
                'order_id' => $orderId,
                'pregame_id' => $pregame->id
            ]);
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
}
