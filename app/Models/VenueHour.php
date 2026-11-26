<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueHour extends Model
{
    public $timestamps = false;

    protected $fillable = ['weekday', 'opens_at', 'closes_at', 'closed'];

    protected $casts = ['closed' => 'boolean'];
}
