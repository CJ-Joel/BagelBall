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

    // Show the logged webhook payloads
    public function showLog()
    {
        $log = \Illuminate\Support\Facades\Storage::exists('eventbrite_webhook.log') ? \Illuminate\Support\Facades\Storage::get('eventbrite_webhook.log') : '';
        return view('eventbrite.webhook_log', ['log' => $log]);
    }
}
