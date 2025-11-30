<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingEventbriteOrder extends Model
{
    protected $table = 'pending_eventbrite_orders';
    
    protected $fillable = [
        'eventbrite_order_id',
        'eventbrite_event_id',
        'api_url',
        'retry_count',
        'max_retries',
        'status',
        'last_error',
    ];
}
