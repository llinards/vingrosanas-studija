<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    /**
     * Get all settings grouped by their group key, with caching.
     *
     * @return Collection<string, Collection<string, string|null>>
     */
    public static function allGrouped(): Collection
    {
        return Cache::rememberForever('site_settings', function () {
            return static::all()
                ->groupBy('group')
                ->map(fn (Collection $items) => $items->pluck('value', 'key'));
        });
    }

    /**
     * Get all settings for a specific group as a keyed collection.
     *
     * @return Collection<string, string|null>
     */
    public static function getGroup(string $group): Collection
    {
        return static::allGrouped()->get($group, collect());
    }

    /**
     * Get a single setting value by group and key.
     */
    public static function getValue(string $group, string $key, ?string $default = null): ?string
    {
        return static::getGroup($group)->get($key, $default);
    }

    /**
     * Upsert multiple settings for a group and bust the cache.
     *
     * @param  array<string, array{value: string|null, type?: string}>  $data
     */
    public static function setGroup(string $group, array $data): void
    {
        DB::transaction(function () use ($group, $data) {
            foreach ($data as $key => $entry) {
                static::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'value' => $entry['value'] ?? null,
                        'type' => $entry['type'] ?? 'string',
                    ],
                );
            }
        });

        Cache::forget('site_settings');
    }
}
