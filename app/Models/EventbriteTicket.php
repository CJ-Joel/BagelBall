<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventbriteTicket extends Model
{
    protected $fillable = [
        'pregame_id',
        'eventbrite_ticket_id',
        'eventbrite_order_id',
        'first_name',
        'last_name',
        'email',
        'barcode_id',
        'gender',
        'pregame_interest',
        'redeemed_at',
        'order_date'
    ];

    public function pregame(): BelongsTo
    {
        return $this->belongsTo(PreGame::class);
    }

    public function isRedeemed(): bool
    {
        return !is_null($this->redeemed_at);
    }
}
