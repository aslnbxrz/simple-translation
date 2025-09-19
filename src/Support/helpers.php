<?php

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

if (!function_exists('___')) {
    /**
     * Auto-save & translate helper (similar to Laravel's __()).
     * - First checks existing translations (AppLanguageService::getTranslatedList() handles caching internally)
     * - If not found, ensures the key is saved into DB (only if it doesn't exist), then reloads the list
     * - Uses in-request memoization for repeated calls (fastest cache layer)
     */
    function ___(string $key, ?string $scope = null, ?string $locale = null): string
    {
        static $memo = []; // in-request memo: scope|locale|key => value

        $scope ??= Config::get('simple-translation.default_scope', 'app');
        $locale ??= App::getLocale();

        $memoKey = $scope . '|' . $locale . '|' . $key;

        // 0) Check in-request memo first
        if (isset($memo[$memoKey])) {
            return $memo[$memoKey];
        }

        // 1) Look up in the cached list (AppLanguageService manages in-request + driver cache)
        $list = AppLanguageService::getTranslatedList($scope, $locale);
        if (array_key_exists($key, $list)) {
            return $memo[$memoKey] = ($list[$key] ?? $key);
        }

        // 2) If not found, save the key into DB (updateOrCreate) — this will invalidate caches
        AppLanguageService::save($key, $scope);

        // 3) Reload the list and return the translation (or fallback to the key itself)
        $list = AppLanguageService::getTranslatedList($scope, $locale);
        return $memo[$memoKey] = ($list[$key] ?? $key);
    }
}