<?php

namespace App\Models;

use Database\Factories\UnavailableDateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UnavailableDate extends Model
{
    /** @use HasFactory<UnavailableDateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Coach, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * Scope to records covering the given date.
     *
     * @param  Builder<UnavailableDate>  $query
     * @return Builder<UnavailableDate>
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        $dateString = $date->toDateString();

        return $query->whereDate('start_date', '<=', $dateString)->whereDate('end_date', '>=', $dateString);
    }

    /**
     * Scope to studio-wide closures (no coach assigned).
     *
     * @param  Builder<UnavailableDate>  $query
     * @return Builder<UnavailableDate>
     */
    public function scopeStudioWide(Builder $query): Builder
    {
        return $query->whereNull('coach_id');
    }

    /**
     * Scope to a specific coach's unavailable dates.
     *
     * @param  Builder<UnavailableDate>  $query
     * @return Builder<UnavailableDate>
     */
    public function scopeForCoach(Builder $query, int $coachId): Builder
    {
        return $query->where('coach_id', $coachId);
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
