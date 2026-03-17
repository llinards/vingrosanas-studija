<?php

use App\Models\SiteSetting;

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
