<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<MembershipAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(MembershipAuditLog::class);
    }

    /**
     * Get the number of sessions that have been booked (active bookings).
     */
    public function sessionsUsed(): int
    {
        return $this->bookings()
            ->whereNotIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::Failed])
            ->count();
    }

    /**
     * Get the number of remaining sessions available.
     */
    public function sessionsRemaining(): int
    {
        return $this->sessions_total - $this->sessionsUsed();
    }

    /**
     * Check if the membership is currently active.
     */
    public function isActive(): bool
    {
        return $this->payment_status === PaymentStatus::Paid
            && $this->period_start->lte(today())
            && $this->period_end->gte(today());
    }

    /**
     * Check if the membership payment is still pending and not expired.
     */
    public function isPendingPayment(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Check if the membership has expired without payment.
     */
    public function isExpired(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Mark the membership as paid with the given payment reference.
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
     * Mark the membership as refunded with the given refund reference.
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
     * Scope to only include active memberships.
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::Paid)
            ->where('period_start', '<=', today())
            ->where('period_end', '>=', today());
    }

    /**
     * Scope to filter memberships by email.
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to filter memberships by a search term across name, surname, phone, and email.
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
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
     * Get the membership tier label from the linked service.
     */
    public function tierLabel(): string
    {
        return $this->service?->name ?? '—';
    }

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'sessions_total' => 'integer',
            'price' => 'integer',
            'expires_at' => 'datetime',
            'payment_reminder_sent_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
