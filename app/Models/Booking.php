<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Check if the booking payment is still pending and not expired.
     */
    public function isPendingPayment(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Check if the booking has expired without payment.
     */
    public function isExpired(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Mark the booking as paid with the given payment reference.
     */
    public function markAsPaid(string $paymentReference): void
    {
        $this->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_reference' => $paymentReference,
            'expires_at' => null,
        ]);
    }

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'booking_date' => 'date',
            'participant_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
