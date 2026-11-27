<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPlayRegistration extends Model
{
    protected $fillable = ['reference', 'customer_name', 'customer_phone', 'total', 'source', 'payment_token', 'payment_qr_payload', 'payment_messenger_url', 'payment_expires_at', 'cancellation_policy'];
    protected $casts = ['total' => 'integer', 'paid_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'payment_expires_at' => 'immutable_datetime', 'checked_in_at' => 'immutable_datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpenPlaySession::class, 'open_play_session_id');
    }

    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->where(fn (Builder $query) =>
            $query->whereNotNull('paid_at')->orWhere('payment_expires_at', '>', now()));
    }

    public function paymentExpired(): bool
    {
        return ! $this->paid_at && $this->payment_expires_at->lte(now());
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
}
