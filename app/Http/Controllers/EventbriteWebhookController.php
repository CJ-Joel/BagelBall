<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventbriteTicket;
use App\Models\PreGame;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventbriteWebhookController extends Controller
{
    // Handle Eventbrite order.placed webhook
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        // Log ALL webhook calls BEFORE processing
        \Log::info('=== WEBHOOK REQUEST RECEIVED ===', [
            'method' => $request->method(),
            'path' => $request->path(),
            'content_type' => $request->header('Content-Type'),
            'payload_keys' => array_keys($payload),
            'full_payload' => $payload,
        ]);

        // Log all webhook calls for debugging - ALWAYS log
        Log::info('Eventbrite webhook received - raw payload', [
            'keys' => array_keys($payload),
            'payload' => $payload
        ]);

        // Check if this is an order.placed webhook with api_url
        if (isset($payload['api_url']) && isset($payload['config']['action'])) {
            $action = $payload['config']['action'];
            Log::info('Webhook action detected', ['action' => $action]);
            
            // Process any order.* event (placed, updated, changed, refunded, etc.)
            if (strpos($action, 'order.') === 0) {
                $apiUrl = $payload['api_url'];
                
                // Extract order ID from API URL (e.g., https://www.eventbriteapi.com/v3/orders/13830202873/)
                preg_match('/orders\/(\d+)/', $apiUrl, $matches);
                $orderId = $matches[1] ?? null;
                
                if (!$orderId) {
                    Log::error('Could not extract order_id from webhook', [
                        'api_url' => $apiUrl,
                        'extracted_order_id' => $orderId,
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'Invalid order_id'], 400);
                }
                
                Log::info('Processing order event', ['action' => $action, 'order_id' => $orderId]);
                $this->processOrderWebhook($orderId, $apiUrl);
                return response()->json(['status' => 'success', 'message' => 'Order processed']);
            }
            
            // Handle attendee.updated events - when attendee details change
            if (strpos($action, 'attendee.') === 0) {
                $apiUrl = $payload['api_url'];
                
                // Extract attendee ID and event ID from API URL
                // e.g., https://www.eventbriteapi.com/v3/events/123456789/attendees/9876543210/
                preg_match('/events\/(\d+)\/attendees\/(\d+)/', $apiUrl, $matches);
                $eventId = $matches[1] ?? null;
                $attendeeId = $matches[2] ?? null;
                
                if (!$eventId || !$attendeeId) {
                    Log::warning('Could not extract event_id or attendee_id from webhook', [
                        'api_url' => $apiUrl,
                        'event_id' => $eventId,
                        'attendee_id' => $attendeeId,
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'Invalid attendee event'], 400);
                }
                
                Log::info('Processing attendee event', ['action' => $action, 'event_id' => $eventId, 'attendee_id' => $attendeeId]);
                $this->processAttendeeWebhook($eventId, $attendeeId, $apiUrl);
                return response()->json(['status' => 'success', 'message' => 'Attendee processed']);
            }
        }

        Log::warning('Eventbrite webhook received but not processed', [
            'has_api_url' => isset($payload['api_url']),
            'has_config_action' => isset($payload['config']['action']) ?? false,
            'payload_keys' => array_keys($payload),
        ]);
        return response()->json(['status' => 'ignored']);
    }

    /**
     * Process order webhook event
     */
    private function processOrderWebhook(string $orderId, string $apiUrl): void
    {
        try {
            $token = config('services.eventbrite.token');
            if (!$token) {
                Log::error('Eventbrite token not configured');
                return;
            }
            
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->get($apiUrl . '?expand=attendees');
            
            if (!$response->successful()) {
                Log::error('Failed to fetch order details from API', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                ]);
                return;
            }
            
            $orderData = $response->json();
            $eventId = $orderData['event_id'] ?? null;
            
            if (!$eventId) {
                Log::error('Could not extract event_id from API response', [
                    'order_id' => $orderId,
                    'api_url' => $apiUrl,
                ]);
                return;
            }
            
            $attendees = $orderData['attendees'] ?? [];
            $this->storeTickets($orderId, $eventId, $attendees);
        } catch (\Exception $e) {
            Log::error('Exception processing order webhook', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Process attendee webhook event - handles attendee.updated events
     */
    private function processAttendeeWebhook(string $eventId, string $attendeeId, string $apiUrl): void
    {
        try {
            $token = config('services.eventbrite.token');
            if (!$token) {
                Log::error('Eventbrite token not configured');
                return;
            }
            
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->get($apiUrl);
            
            if (!$response->successful()) {
                Log::error('Failed to fetch attendee details from API', [
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                    'status' => $response->status(),
                ]);
                return;
            }
            
            $attendeeData = $response->json();
            // Debug: log full attendee API response to help diagnose status fields
            Log::debug('Attendee API response', ['attendee' => $attendeeData]);
            $orderId = $attendeeData['order_id'] ?? null;
            
            if (!$orderId) {
                Log::error('Could not extract order_id from attendee API response', [
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                ]);
                return;
            }
            
            // Update the single attendee
            $this->storeTickets($orderId, $eventId, [$attendeeData]);
            
            Log::info('Attendee updated from webhook', [
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception processing attendee webhook', [
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Store tickets from attendee data
     */
    private function storeTickets(string $orderId, string $eventId, array $attendees): void
    {
        if (empty($attendees)) {
            Log::warning('No attendees to store', ['order_id' => $orderId]);
            return;
        }

        Log::info('Storing tickets from webhook', [
            'order_id' => $orderId,
            'event_id' => $eventId,
            'attendee_count' => count($attendees),
        ]);

        foreach ($attendees as $attendee) {
            $eventbriteTicketId = $attendee['id'] ?? null;
            
            if (!$eventbriteTicketId) {
                Log::warning('Attendee missing ticket ID');
                continue;
            }
            
            $profile = $attendee['profile'] ?? [];
            
            // Parse the datetime from Eventbrite format to MySQL format
            $orderDate = null;
            if (isset($attendee['created'])) {
                try {
                    $orderDate = Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning('Failed to parse order_date', ['error' => $e->getMessage()]);
                }
            }
            
            $updateData = [
                'eventbrite_order_id' => $orderId,
                'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                'email' => $profile['email'] ?? $attendee['email'] ?? null,
                'order_date' => $orderDate,
                'barcode_id' => $this->extractBarcodeId($attendee),
                'gender' => $this->extractGender($attendee),
            ];
            // Detect used/checked-in status and set redeemed_at when appropriate.
            $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
            $barcodeUsed = false;
            $truthyStatuses = ['used','scanned','redeemed','consumed','checked_in','checked-in','checkedin'];

            if (!empty($attendee['barcodes']) && is_array($attendee['barcodes'])) {
                foreach ($attendee['barcodes'] as $b) {
                    $candidates = is_array($b) ? array_values($b) : [$b];
                    foreach ($candidates as $val) {
                        if (!is_scalar($val)) continue;
                        $s = strtolower((string)$val);
                        foreach ($truthyStatuses as $tok) {
                            if (strpos($s, $tok) !== false) { $barcodeUsed = true; break 3; }
                        }
                    }
                }
            }
            if (! $barcodeUsed) {
                if (!empty($attendee['checked_in']) && ($attendee['checked_in'] === true || strtolower((string)$attendee['checked_in']) === 'true')) {
                    $barcodeUsed = true;
                }
            }
            if (! $barcodeUsed && !empty($attendee['status'])) {
                $s = strtolower((string)$attendee['status']);
                foreach ($truthyStatuses as $tok) {
                    if (strpos($s, $tok) !== false) { $barcodeUsed = true; break; }
                }
            }

            if ($barcodeUsed) {
                Log::info('Eventbrite barcode reported as used (storeTickets)', [
                    'eventbrite_ticket_id' => $eventbriteTicketId,
                    'existing_ticket_id' => $existingTicket->id ?? null,
                    'existing_redeemed_at' => $existingTicket->redeemed_at ?? null,
                ]);
                if (! $existingTicket || is_null($existingTicket->redeemed_at)) {
                    $updateData['redeemed_at'] = Carbon::now()->format('Y-m-d H:i:s');
                    Log::info('Setting redeemed_at from Eventbrite webhook (storeTickets)', [
                        'eventbrite_ticket_id' => $eventbriteTicketId,
                        'redeemed_at' => $updateData['redeemed_at'],
                    ]);
                } else {
                    Log::info('Not overwriting existing redeemed_at (storeTickets)', [
                        'eventbrite_ticket_id' => $eventbriteTicketId,
                        'existing_redeemed_at' => $existingTicket->redeemed_at,
                    ]);
                }
            } else {
                Log::info('Eventbrite barcode not marked used (storeTickets)', ['eventbrite_ticket_id' => $eventbriteTicketId]);
            }
            
            // Check if ticket already exists to log if this is an update
            $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
            $isUpdate = $existingTicket !== null;
            
            $ticket = EventbriteTicket::updateOrCreate(
                ['eventbrite_ticket_id' => $eventbriteTicketId],
                $updateData
            );
            
            Log::info('Ticket stored from webhook', [
                'ticket_id' => $eventbriteTicketId,
                'order_id' => $orderId,
                'is_update' => $isUpdate,
                'first_name' => $updateData['first_name'],
                'last_name' => $updateData['last_name'],
                'email' => $updateData['email'],
                'database_id' => $ticket->id,
            ]);
        }
    }

    /**
     * Try to extract a barcode id from common Eventbrite attendee/order payload shapes.
     */
    private function extractBarcodeId(array $attendee): ?string
    {
        if (!empty($attendee['barcodes']) && is_array($attendee['barcodes'])) {
            foreach ($attendee['barcodes'] as $b) {
                if (!empty($b['barcode'])) return $b['barcode'];
                if (!empty($b['value'])) return $b['value'];
                if (!empty($b['code'])) return $b['code'];
            }
        }

        if (!empty($attendee['barcode'])) return $attendee['barcode'];
        if (!empty($attendee['code'])) return $attendee['code'];

        return null;
    }

    /**
     * Try to extract gender from profile or answers in the attendee payload.
     */
    private function extractGender(array $attendee): ?string
    {
        $profile = $attendee['profile'] ?? null;

        if (is_array($profile)) {
            if (!empty($profile['gender'])) return $profile['gender'];

            if (!empty($profile['answers']) && is_array($profile['answers'])) {
                foreach ($profile['answers'] as $answer) {
                    $question = $answer['question'] ?? $answer['label'] ?? '';
                    if ($question && stripos($question, 'gender') !== false) {
                        return $answer['answer'] ?? $answer['value'] ?? null;
                    }
                }
            }

            foreach (['gender_raw', 'sex'] as $k) {
                if (!empty($profile[$k])) return $profile[$k];
            }
        }

        return null;
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
                    $orderDate = null;
                    if (isset($attendee['created'])) {
                        try {
                            $orderDate = \Carbon\Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            // If parsing fails, leave as null
                        }
                    }
                    
                    // Check if ticket already exists
                    $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
                    
                    // Build update array, preserving existing redeemed_at if already set
                    $updateData = [
                        'eventbrite_order_id' => $orderId,
                        'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                        'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                        'email' => $profile['email'] ?? $attendee['email'] ?? null,
                        'order_date' => $orderDate,
                        'barcode_id' => $this->extractBarcodeId($attendee),
                        'gender' => $this->extractGender($attendee),
                        'pregame_interest' => $this->extractPregameInterest($attendee),
                    ];

                    // Detect barcode/attendee status indicating the ticket has been used.
                    // Eventbrite payloads vary; check several possible places and status names.
                    $barcodeUsed = false;
                    $truthyStatuses = ['used','scanned','redeemed','consumed','checked_in','checked-in','checkedin'];

                    // Check barcodes array entries for any status-like fields
                    if (!empty($attendee['barcodes']) && is_array($attendee['barcodes'])) {
                        foreach ($attendee['barcodes'] as $b) {
                            // common keys that might contain usage info
                            $candidates = [];
                            if (is_array($b)) {
                                $candidates = array_values($b);
                            } else {
                                $candidates = [$b];
                            }
                            foreach ($candidates as $val) {
                                if (!is_scalar($val)) continue;
                                $s = strtolower((string)$val);
                                foreach ($truthyStatuses as $tok) {
                                    if (strpos($s, $tok) !== false) {
                                        $barcodeUsed = true;
                                        break 3; // exit all loops
                                    }
                                }
                            }
                        }
                    }

                    // Check common top-level attendee flags/fields
                    if (! $barcodeUsed) {
                        if (!empty($attendee['checked_in']) && ($attendee['checked_in'] === true || strtolower((string)$attendee['checked_in']) === 'true')) {
                            $barcodeUsed = true;
                        }
                    }
                    if (! $barcodeUsed && !empty($attendee['status'])) {
                        $s = strtolower((string)$attendee['status']);
                        foreach ($truthyStatuses as $tok) {
                            if (strpos($s, $tok) !== false) {
                                $barcodeUsed = true;
                                break;
                            }
                        }
                    }
                    // Some payloads might include a single `barcode` or `code` object/string
                    if (! $barcodeUsed) {
                        $single = $attendee['barcode'] ?? $attendee['code'] ?? null;
                        if ($single && is_array($single)) {
                            foreach ($single as $v) {
                                if (!is_scalar($v)) continue;
                                $s = strtolower((string)$v);
                                foreach ($truthyStatuses as $tok) {
                                    if (strpos($s, $tok) !== false) { $barcodeUsed = true; break 2; }
                                }
                            }
                        }
                    }

                    if ($barcodeUsed) {
                        Log::info('Eventbrite barcode reported as used', [
                            'eventbrite_ticket_id' => $eventbriteTicketId,
                            'existing_ticket_id' => $existingTicket->id ?? null,
                            'existing_redeemed_at' => $existingTicket->redeemed_at ?? null,
                        ]);

                        // Only set redeemed_at when the DB value is currently null
                        if (! $existingTicket || is_null($existingTicket->redeemed_at)) {
                            $updateData['redeemed_at'] = Carbon::now()->format('Y-m-d H:i:s');
                            Log::info('Setting redeemed_at from Eventbrite webhook', [
                                'eventbrite_ticket_id' => $eventbriteTicketId,
                                'redeemed_at' => $updateData['redeemed_at'],
                            ]);
                        } else {
                            Log::info('Not overwriting existing redeemed_at', [
                                'eventbrite_ticket_id' => $eventbriteTicketId,
                                'existing_redeemed_at' => $existingTicket->redeemed_at,
                            ]);
                        }
                    } else {
                        Log::info('Eventbrite barcode not marked used', [
                            'eventbrite_ticket_id' => $eventbriteTicketId,
                        ]);
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

    /**
     * Extract an attendee's answer to any 'pregame' related custom question.
     */
    private function extractPregameInterest(array $attendee): ?string
    {
        $answers = [];
        if (!empty($attendee['profile']) && !empty($attendee['profile']['answers']) && is_array($attendee['profile']['answers'])) {
            $answers = $attendee['profile']['answers'];
        }
        if (!empty($attendee['answers']) && is_array($attendee['answers'])) {
            $answers = array_merge($answers, $attendee['answers']);
        }

        foreach ($answers as $ans) {
            $question = $ans['question'] ?? $ans['label'] ?? '';
            $value = $ans['answer'] ?? $ans['value'] ?? null;
            if (!$question || $value === null) continue;

            $q = strtolower($question);
            if (stripos($q, 'pregame') !== false || stripos($q, 'pre-game') !== false || stripos($q, 'join a pre') !== false || stripos($q, 'interested') !== false) {
                return is_array($value) ? json_encode($value) : (string)$value;
            }
        }

        return null;
    }
}
