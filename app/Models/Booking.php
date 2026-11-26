<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'reference', 'starts_at', 'ends_at', 'customer_name', 'customer_phone', 'total', 'source', 'cancellation_policy',
        'payment_token', 'payment_qr_payload', 'payment_messenger_url', 'payment_expires_at',
    ];

    protected $casts = [
        'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
        'paid_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'payment_expires_at' => 'immutable_datetime',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->where(function (Builder $query) {
            $query->whereNull('payment_expires_at')->orWhereNotNull('paid_at')->orWhere('payment_expires_at', '>', now());
        });
    }

    public function paymentStatus(): string
    {
        return match (true) {
            (bool) $this->cancelled_at => 'cancelled',
            (bool) $this->paid_at => 'confirmed',
            $this->paymentExpired() => 'expired',
            default => 'pending',
        };
    }

    public function paymentExpired(): bool
    {
        return $this->payment_expires_at && ! $this->paid_at && $this->payment_expires_at->lte(now());
    }
}
