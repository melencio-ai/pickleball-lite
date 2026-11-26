<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['court_id', 'weekdays', 'starts_at', 'ends_at', 'hourly_rate'];

    protected $casts = ['weekdays' => 'array', 'court_id' => 'integer', 'hourly_rate' => 'integer'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
