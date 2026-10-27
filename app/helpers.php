<?php

use App\Models\SiteSetting;
use Illuminate\Support\Number;

if (! function_exists('site')) {
    /**
     * Get a site setting value by group and optional key.
     * Returns the full group collection if no key is provided.
     */
    function site(string $group, ?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return SiteSetting::getGroup($group)->toArray();
        }

        return SiteSetting::getValue($group, $key, $default);
    }
}

if (! function_exists('price_label')) {
    /**
     * Format a price in cents for customers, showing a free price as "Bez maksas".
     */
    function price_label(int $priceInCents): string
    {
        return $priceInCents === 0
            ? __('Bez maksas')
            : Number::currency($priceInCents / 100, 'EUR');
    }
}
