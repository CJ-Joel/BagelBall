<?php

use App\Models\EventbriteTicket;
use App\Models\PreGame;
use Illuminate\Support\Facades\Http;

test('webhook processes order.placed and triggers sync', function () {
    // Create a pregame with an eventbrite event ID
    $pregame = PreGame::create([
        'name' => 'Test PreGame',
        'description' => 'Test Description',
        'start_time' => now()->addDays(1),
        'location' => 'Test Location',
        'capacity' => 100,
        'price' => 10.00,
        'eventbrite_event_id' => '123456789',
    ]);

    // Mock the Eventbrite API responses
    Http::fake([
        // Order API response
        'www.eventbriteapi.com/v3/orders/order123/*' => Http::response([
            'id' => 'order123',
            'event_id' => '123456789',
            'attendees' => [
                [
                    'id' => 'attendee1',
                    'created' => now()->toIso8601String(),
                    'profile' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
        ]),
        // Event orders API response (sync)
        'www.eventbriteapi.com/v3/events/123456789/orders/*' => Http::response([
            'pagination' => ['has_more_items' => false],
            'orders' => [
                [
                    'id' => 'order123',
                    'attendees' => [
                        [
                            'id' => 'attendee1',
                            'created' => now()->toIso8601String(),
                            'profile' => [
                                'first_name' => 'John',
                                'last_name' => 'Doe',
                                'email' => 'john@example.com',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'order456',
                    'attendees' => [
                        [
                            'id' => 'attendee2',
                            'created' => now()->toIso8601String(),
                            'profile' => [
                                'first_name' => 'Jane',
                                'last_name' => 'Smith',
                                'email' => 'jane@example.com',
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Set the Eventbrite token in config
    config(['services.eventbrite.token' => 'test-token']);

    // Send webhook request
    $response = $this->postJson('/webhooks/eventbrite', [
        'api_url' => 'https://www.eventbriteapi.com/v3/orders/order123/',
        'config' => [
            'action' => 'order.placed',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'success', 'message' => 'Order processed and tickets synced']);

    // Verify both tickets were synced (one from order processing + one more from sync)
    expect(EventbriteTicket::count())->toBe(2);
    expect(EventbriteTicket::where('eventbrite_ticket_id', 'attendee1')->exists())->toBeTrue();
    expect(EventbriteTicket::where('eventbrite_ticket_id', 'attendee2')->exists())->toBeTrue();
});

test('webhook returns success without token', function () {
    // Clear any existing token
    config(['services.eventbrite.token' => null]);

    $response = $this->postJson('/webhooks/eventbrite', [
        'api_url' => 'https://www.eventbriteapi.com/v3/orders/order123/',
        'config' => [
            'action' => 'order.placed',
        ],
    ]);

    $response->assertStatus(500)
        ->assertJson(['status' => 'error', 'message' => 'Token not configured']);
});

test('webhook handles non-order events gracefully', function () {
    $response = $this->postJson('/webhooks/eventbrite', [
        'api_url' => 'https://www.eventbriteapi.com/v3/orders/order123/',
        'config' => [
            'action' => 'order.updated',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'success']);
});
