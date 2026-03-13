<?php

namespace App\Models;

use Database\Factories\ServicePriceTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceTier extends Model
{
    /** @use HasFactory<ServicePriceTierFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'participant_count' => 'integer',
        ];
    }
}
