<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckinAudit extends Model
{
    protected $table = 'checkin_audits';

    protected $fillable = [
        'eventbrite_ticket_id',
        'barcode_id',
        'action',
        'status',
        'actor',
        'ip_address',
        'user_agent',
    ];
}
