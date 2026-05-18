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
     * Get the maximum number of participants allowed for this service.
     */
    public function maxParticipants(): int
    {
        return $this->priceTiers()->max('participant_count') ?? 1;
    }

    /**
     * Scope to only include membership services.
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeMembership(Builder $query): Builder
    {
        return $query->where('is_membership', true);
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
            'position' => 'integer',
        ];
    }
}
