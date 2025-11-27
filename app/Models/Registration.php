<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    protected $fillable = [
        'pregame_id', 'name', 'email', 'friend_name', 'friend_email', 'payment_status', 'eventbrite_ticket_id', 'eventbrite_order_id'
    ];

    public function pregame(): BelongsTo
    {
        return $this->belongsTo(PreGame::class, 'pregame_id');
    }

    public function eventbriteTicket(): BelongsTo
    {
        return $this->belongsTo(EventbriteTicket::class, 'eventbrite_ticket_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isRedeemed(): bool
    {
        return $this->payment_status === 'redeemed';
    }
}
