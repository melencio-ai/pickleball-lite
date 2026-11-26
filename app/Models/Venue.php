<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'timezone'];

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(VenueHour::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
