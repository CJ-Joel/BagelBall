<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PreGame extends Model
{
    protected $table = 'pregames';
    protected $fillable = [
        'name', 'description', 'start_time', 'location', 'capacity', 'price', 'eventbrite_event_id'
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'pregame_id');
    }

    public function eventbriteTicket(): HasOne
    {
        return $this->hasOne(EventbriteTicket::class);
    }

    public function spotsRemaining(): int
    {
        return $this->capacity - $this->registrations()->count();
    }

    public function isFull(): bool
    {
        return $this->spotsRemaining() <= 0;
    }

    public function label(): string
    {
        return $this->isFull() ? 'Full' : 'Open';
    }
}
