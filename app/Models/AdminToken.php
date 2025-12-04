<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminToken extends Model
{
    protected $table = 'admin_tokens';

    protected $fillable = [
        'token_hash',
        'device',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
