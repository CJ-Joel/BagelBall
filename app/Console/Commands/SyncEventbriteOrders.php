<?php

namespace App\Console\Commands;

use App\Models\EventbriteTicket;
use App\Models\PreGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncEventbriteOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eventbrite:sync {event_id?} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Eventbrite orders to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('services.eventbrite.token');
        
        if (!$token) {
            $this->error('Eventbrite token not configured in .env file');
            return 1;
        }

        if ($this->option('all')) {
            $this->info('Syncing all PreGames with Eventbrite event IDs...');
            $pregames = PreGame::whereNotNull('eventbrite_event_id')->get();
            
            if ($pregames->isEmpty()) {
                $this->error('No PreGames found with eventbrite_event_id set');
                return 1;
            }

            foreach ($pregames as $pregame) {
                $this->info("Syncing PreGame: {$pregame->name} (Event ID: {$pregame->eventbrite_event_id})");
                $this->syncEventOrders($pregame->eventbrite_event_id, $pregame->id, $token);
            }
        } else {
            $eventId = $this->argument('event_id');
            
            if (!$eventId) {
                $this->error('Please provide an event_id or use --all flag');
                return 1;
            }

            $pregame = PreGame::where('eventbrite_event_id', $eventId)->first();
            
            if (!$pregame) {
                $this->error("No PreGame found with eventbrite_event_id: {$eventId}");
                return 1;
            }

            $this->info("Syncing Event ID: {$eventId}");
            $this->syncEventOrders($eventId, $pregame->id, $token);
        }

        $this->info('Sync completed!');
        return 0;
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

    /**
     * Sync orders for a specific event
     */
    private function syncEventOrders($eventId, $pregameId, $token)
    {
        $page = 1;
        $hasMorePages = true;
        $totalOrders = 0;
        $totalAttendees = 0;

        while ($hasMorePages) {
            $this->info("  Fetching page {$page}...");
            
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->get("https://www.eventbriteapi.com/v3/events/{$eventId}/orders/", [
                    'page' => $page,
                    'expand' => 'attendees'
                ]);

            if (!$response->successful()) {
                $this->error("  Failed to fetch orders: " . $response->status());
                Log::error('Eventbrite sync failed', [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            
            if (empty($orders)) {
                $this->info("  No orders found on page {$page}");
                break;
            }

            foreach ($orders as $order) {
                $orderId = $order['id'];
                $attendees = $order['attendees'] ?? [];
                
                $this->info("  Processing order: {$orderId} ({count($attendees)} attendees)");
                $totalOrders++;

                foreach ($attendees as $attendee) {
                    $eventbriteTicketId = $attendee['id'] ?? null;
                    
                    if (!$eventbriteTicketId) {
                        continue;
                    }

                    $profile = $attendee['profile'] ?? [];
                    
                    // Find existing ticket to preserve redeemed_at if already set
                    $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
                    
                    $updateData = [
                        'pregame_id' => $pregameId,
                        'eventbrite_order_id' => $orderId,
                        'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                        'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                        'email' => $profile['email'] ?? $attendee['email'] ?? null,
                        'order_date' => $attendee['created'] ?? null,
                        'barcode_id' => $this->extractBarcodeId($attendee),
                        'gender' => $this->extractGender($attendee),
                        'pregame_interest' => $this->extractPregameInterest($attendee),
                    ];
                    
                    // Only set redeemed_at if it's not already set
                    if (!$existingTicket || is_null($existingTicket->redeemed_at)) {
                        $updateData['redeemed_at'] = null;
                    }
                    
                    $ticket = EventbriteTicket::updateOrCreate(
                        [
                            'eventbrite_ticket_id' => $eventbriteTicketId,
                        ],
                        $updateData
                    );
                    
                    $totalAttendees++;
                    $this->line("    ✓ Synced ticket: {$eventbriteTicketId}");
                }
            }

            // Check if there are more pages
            $pagination = $data['pagination'] ?? [];
            $hasMorePages = ($pagination['has_more_items'] ?? false);
            $page++;

            // Safety limit
            if ($page > 100) {
                $this->warn('  Reached page limit (100). Stopping.');
                break;
            }
        }

        $this->info("  Summary: {$totalOrders} orders, {$totalAttendees} attendees synced");
    }

    /**
     * Try to extract whether an attendee answered that they're interested in joining a pregame.
     * Looks through common payload shapes: profile.answers or attendee.answers, matches question text.
     */
    private function extractPregameInterest(array $attendee): ?string
    {
        $sources = [];
        if (!empty($attendee['profile']) && is_array($attendee['profile'])) {
            $profile = $attendee['profile'];
            if (!empty($profile['answers']) && is_array($profile['answers'])) {
                $sources[] = $profile['answers'];
            }
        }

        if (!empty($attendee['answers']) && is_array($attendee['answers'])) {
            $sources[] = $attendee['answers'];
        }

        foreach ($sources as $answers) {
            foreach ($answers as $answer) {
                $question = $answer['question'] ?? $answer['label'] ?? '';
                $value = $answer['answer'] ?? $answer['value'] ?? null;
                if (!$question || $value === null) continue;

                $q = strtolower($question);
                if (stripos($q, 'pregame') !== false || stripos($q, 'pre-game') !== false || stripos($q, 'join a pre') !== false || stripos($q, 'interested in hosting') !== false || stripos($q, 'interested') !== false) {
                    return is_array($value) ? json_encode($value) : (string)$value;
                }
            }
        }

        return null;
    }
}
