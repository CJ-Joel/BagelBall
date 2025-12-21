<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PreGame extends Model
{
    protected $table = 'pregames';
    protected $fillable = [
        'name', 'description', 'start_time', 'location', 'capacity', 'price', 'eventbrite_event_id', 'partiful_url'
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
        // Count both registrants and their friends
        $registrantCount = $this->registrations()
            ->selectRaw('SUM(
                CASE WHEN email IS NOT NULL AND email <> "" THEN 1 ELSE 0 END +
                CASE WHEN friend_email IS NOT NULL AND friend_email <> "" THEN 1 ELSE 0 END
            ) as total_count')
            ->value('total_count') ?? 0;
        
        return max(0, $this->capacity - $registrantCount);
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
