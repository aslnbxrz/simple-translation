<?php

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

if (!function_exists('___')) {
    /**
     * File-first translation helper with DB fallback.
     * - Reads from per-scope file store
     * - Falls back to DB, writes file if found
     * - If missing everywhere, creates DB key and writes key-as-value to file
     */
    function ___(string $key, ?string $scope = null, ?string $locale = null): string
    {
        $scope ??= (string)Config::get('simple-translation.default_scope', 'app');
        $locale ??= App::getLocale();

        return AppLanguageService::translate($key, $scope, $locale);
    }
}