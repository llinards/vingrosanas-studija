<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
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
     * Scope to filter bookings by a search term across name, surname, phone, and email.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $subQuery) use ($term) {
            $subQuery->where('name', 'like', '%'.$term.'%')
                ->orWhere('surname', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%');
        });
    }

    /**
     * Scope to only include active bookings that occupy capacity.
     *
     * Excludes refunded, failed, and expired pending bookings.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('payment_status', [
            PaymentStatus::Refunded,
            PaymentStatus::Failed,
        ])->where(function (Builder $query) {
            $query->where('payment_status', '!=', PaymentStatus::Pending)
                ->orWhereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
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

    /**
     * Check if the booking is eligible for a refund.
     *
     * A booking can be refunded if it is paid and the service
     * is more than 24 hours away.
     */
    public function isRefundable(): bool
    {
        if ($this->payment_status !== PaymentStatus::Paid) {
            return false;
        }

        if (! $this->payment_reference) {
            return false;
        }

        return $this->getServiceDateTime()->isAfter(now()->addHours(24));
    }

    /**
     * Mark the booking as refunded with the given refund reference.
     */
    public function markAsRefunded(string $refundReference): void
    {
        $this->update([
            'payment_status' => PaymentStatus::Refunded,
            'refund_reference' => $refundReference,
            'refunded_at' => now(),
        ]);
    }

    /**
     * Get the full service date and time as a Carbon instance.
     */
    public function getServiceDateTime(): \Illuminate\Support\Carbon
    {
        return $this->booking_date->copy()->setTimeFromTimeString($this->schedule->start_time);
    }

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'booking_date' => 'date',
            'participant_count' => 'integer',
            'expires_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
