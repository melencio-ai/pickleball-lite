<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlaySession extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at', 'capacity', 'price', 'skill_level', 'series_id', 'cancelled_at'];
    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'price' => 'integer', 'capacity' => 'integer'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(Court::class, 'open_play_session_courts');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(OpenPlayRegistration::class);
    }

    public function spotsLeft(): int
    {
        $occupied = $this->active_count ?? $this->registrations()->occupying()->count();

        return max(0, $this->capacity - $occupied);
    }

    public function levelLabel(): string
    {
        return $this->skill_level === 'all' ? 'All skill levels' : ucfirst($this->skill_level);
    }
}
