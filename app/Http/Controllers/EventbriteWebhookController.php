<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventbriteTicket;
use App\Models\PreGame;

class EventbriteWebhookController extends Controller
{
    // Handle Eventbrite order.placed webhook
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        // Log all webhook calls for debugging
        \Illuminate\Support\Facades\Log::info('Eventbrite webhook received', ['payload' => $payload]);

        // Example: Loop through attendees/tickets in the order
        if (isset($payload['attendees']) && is_array($payload['attendees'])) {
            foreach ($payload['attendees'] as $attendee) {
                $ticketId = $attendee['ticket_class_id'] ?? null;
                $eventbriteTicketId = $attendee['id'] ?? null;
                $redeemedAt = $attendee['created'] ?? null;
                $eventId = $payload['event_id'] ?? null;

                // Find the matching PreGame (if you map Eventbrite event_id to PreGame)
                $pregame = PreGame::where('eventbrite_event_id', $eventId)->first();
                if ($pregame) {
                    EventbriteTicket::updateOrCreate(
                        [
                            'eventbrite_ticket_id' => $eventbriteTicketId,
                            'pregame_id' => $pregame->id,
                        ],
                        [
                            'redeemed_at' => $redeemedAt,
                        ]
                    );
                }
            }
        }

        return response()->json(['status' => 'success']);
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
}
