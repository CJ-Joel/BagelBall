<?php

namespace App\Jobs;

use App\Models\EventbriteTicket;
use App\Models\PendingEventbriteOrder;
use App\Models\PreGame;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessEventbriteOrder implements ShouldQueue
{
    use Queueable;

    public $timeout = 30;
    public $tries = 1; // Don't retry on job failure - we handle retries manually

    private $pendingOrder;

    /**
     * Create a new job instance.
     */
    public function __construct(PendingEventbriteOrder $pendingOrder)
    {
        $this->pendingOrder = $pendingOrder;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessEventbriteOrder job started', [
            'order_id' => $this->pendingOrder->eventbrite_order_id,
            'retry_count' => $this->pendingOrder->retry_count,
            'max_retries' => $this->pendingOrder->max_retries,
        ]);

        try {
            $token = config('services.eventbrite.token');
            
            if (!$token) {
                Log::error('Eventbrite token not configured');
                $this->pendingOrder->update([
                    'status' => 'failed',
                    'last_error' => 'Eventbrite token not configured',
                ]);
                return;
            }

            // Fetch order with attendees
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->timeout(10)
                ->get($this->pendingOrder->api_url . '?expand=attendees');

            if (!$response->successful()) {
                Log::warning('Failed to fetch order from Eventbrite', [
                    'order_id' => $this->pendingOrder->eventbrite_order_id,
                    'status' => $response->status(),
                ]);
                $this->scheduleRetry('HTTP ' . $response->status());
                return;
            }

            $orderData = $response->json();
            $attendees = $orderData['attendees'] ?? [];

            // Check if attendees are available yet
            if (empty($attendees)) {
                Log::info('No attendees yet, scheduling retry', [
                    'order_id' => $this->pendingOrder->eventbrite_order_id,
                    'retry_count' => $this->pendingOrder->retry_count,
                ]);
                $this->scheduleRetry('No attendees available yet');
                return;
            }

            // Process the order now that we have attendees
            $this->processOrder($orderData);

            // Mark as processed
            $this->pendingOrder->update([
                'status' => 'processed',
                'retry_count' => $this->pendingOrder->retry_count + 1,
            ]);

            Log::info('Order processed successfully', [
                'order_id' => $this->pendingOrder->eventbrite_order_id,
                'attendee_count' => count($attendees),
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in ProcessEventbriteOrder', [
                'order_id' => $this->pendingOrder->eventbrite_order_id,
                'error' => $e->getMessage(),
            ]);
            $this->scheduleRetry($e->getMessage());
        }
    }

    /**
     * Schedule a retry if max retries not exceeded
     */
    private function scheduleRetry(string $error): void
    {
        $nextRetry = $this->pendingOrder->retry_count + 1;

        if ($nextRetry >= $this->pendingOrder->max_retries) {
            Log::error('Max retries exceeded for order', [
                'order_id' => $this->pendingOrder->eventbrite_order_id,
                'retry_count' => $nextRetry,
            ]);
            $this->pendingOrder->update([
                'status' => 'failed',
                'retry_count' => $nextRetry,
                'last_error' => $error,
            ]);
            return;
        }

        // Schedule next retry in 10 seconds
        $this->pendingOrder->update([
            'retry_count' => $nextRetry,
            'last_error' => $error,
        ]);

        dispatch(new self($this->pendingOrder))
            ->delay(now()->addSeconds(10));

        Log::info('Scheduled retry for order', [
            'order_id' => $this->pendingOrder->eventbrite_order_id,
            'next_retry_count' => $nextRetry,
            'delay_seconds' => 10,
        ]);
    }

    /**
     * Process order data and store tickets
     */
    private function processOrder(array $orderData): void
    {
        $eventId = $orderData['event_id'] ?? null;
        $orderId = $orderData['id'] ?? null;

        if (!$eventId || !$orderId) {
            Log::warning('Order missing event_id or order id', ['order' => $orderData]);
            return;
        }

        // Find the matching PreGame
        $pregame = PreGame::where('eventbrite_event_id', $eventId)->first();
        
        if (!$pregame) {
            Log::warning('No PreGame found for event_id', [
                'event_id' => $eventId,
                'available_event_ids' => PreGame::pluck('eventbrite_event_id')->toArray()
            ]);
            return;
        }

        // Process attendees
        $attendees = $orderData['attendees'] ?? [];
        Log::info('Processing attendees from job', ['count' => count($attendees)]);
        
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
                    $orderDate = \Carbon\Carbon::parse($attendee['created'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning('Failed to parse order_date', ['error' => $e->getMessage()]);
                }
            }
            
            // Check if ticket already exists
            $existingTicket = EventbriteTicket::where('eventbrite_ticket_id', $eventbriteTicketId)->first();
            
            // Build update array - do NOT set pregame_id here
            $updateData = [
                'eventbrite_order_id' => $orderId,
                'first_name' => $profile['first_name'] ?? $attendee['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? $attendee['last_name'] ?? null,
                'email' => $profile['email'] ?? $attendee['email'] ?? null,
                'order_date' => $orderDate,
            ];
            
            try {
                EventbriteTicket::updateOrCreate(
                    ['eventbrite_ticket_id' => $eventbriteTicketId],
                    $updateData
                );
                
                Log::info('Ticket stored from job', [
                    'ticket_id' => $eventbriteTicketId,
                    'order_id' => $orderId,
                    'first_name' => $updateData['first_name'],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to store ticket from job', [
                    'ticket_id' => $eventbriteTicketId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
