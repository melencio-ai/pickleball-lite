<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'reference', 'starts_at', 'ends_at', 'customer_name', 'customer_phone', 'total', 'source', 'cancellation_policy',
    ];

    protected $casts = [
        'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
        'paid_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
    ];
}
