<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    public const DEFAULT_DURATION_MINUTES = 60;

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * @return BelongsTo<Coach, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return HasMany<ServicePriceTier, $this>
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(ServicePriceTier::class)->orderBy('participant_count');
    }

    /**
     * Whether a session of this class can be part of the given membership:
     * the class is open to memberships and belongs to the membership's category.
     */
    public function isCoveredBy(Service $membership): bool
    {
        return $this->is_membership_eligible
            && ! $this->is_membership
            && (int) $this->service_type_id === (int) $membership->service_type_id;
    }

    /**
     * Get the total price in cents for a booking of the given group size.
     *
     * Falls back to the base price when no tier is defined for that size.
     */
    public function priceFor(int $participantCount): int
    {
        return $this->priceTiers->firstWhere('participant_count', $participantCount)?->price ?? $this->price;
    }

    /**
     * Leave out one-time events: services whose active schedules are all on a specific date.
     *
     * Services with no active schedules are kept.
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeExcludingOneTimeEvents(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('schedules', fn (Builder $schedules) => $schedules->where('is_active', true)->whereNotNull('date'))
            ->orWhereHas('schedules', fn (Builder $schedules) => $schedules->where('is_active', true)->whereNull('date')));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_exclusive' => 'boolean',
            'is_membership_eligible' => 'boolean',
            'is_membership' => 'boolean',
            'notify_coach_on_cancellation' => 'boolean',
            'sessions_count' => 'integer',
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'position' => 'integer',
        ];
    }
}
