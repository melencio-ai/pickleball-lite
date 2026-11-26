<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtBlock extends Model
{
    protected $fillable = ['starts_at', 'ends_at', 'reason'];

    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
}
