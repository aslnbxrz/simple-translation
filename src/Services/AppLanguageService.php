<?php

namespace Aslnbxrz\SimpleTranslation\Services;

use Aslnbxrz\SimpleTranslation\Models\AppLanguage;
use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Models\AppTextTranslation;
use Aslnbxrz\SimpleTranslation\Stores\Contracts\StoreDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Core translation service.
 *
 * Strategy: "File-first, DB as backup".
 * - Prefer translations from JSON store.
 * - If missing, check DB.
 * - If still missing, create key in DB and store default (key itself).
 */
class AppLanguageService
{
    /** In-request memo cache: "scope|locale" => [key => value] */
    private static array $memo = [];

    /**
     * Translate a key for given scope/locale.
     *
     * @param string $key
     * @param string|null $scope
     * @param string|null $locale
     * @return string
     */
    public static function translate(string $key, ?string $scope = null, ?string $locale = null): string
    {
        $scope ??= (string)Config::get('simple-translation.default_scope', 'app');
        $locale ??= App::getLocale();

        // 1) File lookup
        $list = self::list($scope, $locale);
        if (array_key_exists($key, $list)) {
            return $list[$key] ?? $key;
        }

        // 2) DB lookup
        $fromDb = self::lookupDb($key, $scope, $locale);
        if ($fromDb !== null && $fromDb !== '') {
            self::store()->upsert($scope, $locale, $key, $fromDb);
            self::$memo[self::mkMemo($scope, $locale)][$key] = $fromDb;
            return $fromDb;
        }

        // 3) Ensure DB key + fallback default
        self::ensureDbKey($key, $scope);
        self::store()->upsert($scope, $locale, $key, $key);
        self::$memo[self::mkMemo($scope, $locale)][$key] = $key;

        return $key;
    }

    /**
     * Return full translation list for scope+locale (cached).
     *
     * @param string|null $scope
     * @param string|null $locale
     * @return array<string,string>
     */
    public static function list(?string $scope = null, ?string $locale = null): array
    {
        $scope ??= (string)Config::get('simple-translation.default_scope', 'app');
        $locale ??= App::getLocale();

        $m = self::mkMemo($scope, $locale);
        if (isset(self::$memo[$m])) {
            return self::$memo[$m];
        }

        $map = self::store()->read($scope, $locale);
        return self::$memo[$m] = $map;
    }

    /**
     * Export DB → file for all locales of a given scope.
     *
     * @param string $scope
     * @param string[]|null $locales
     * @return bool
     */
    public static function exportScope(string $scope, ?array $locales = null): bool
    {
        $languages = $locales ?: self::getLanguages()->pluck('code')->all();

        $texts = AppText::query()
            ->where('scope', $scope)
            ->get(['id', 'text']);

        $idToKey = $texts->pluck('text', 'id');
        $keys = $texts->pluck('text')->all();

        foreach ($languages as $locale) {
            $map = array_fill_keys($keys, null);

            $trs = AppTextTranslation::query()
                ->where('lang_code', $locale)
                ->whereIn('app_text_id', $texts->pluck('id'))
                ->get(['app_text_id', 'text']);

            foreach ($trs as $tr) {
                $key = $idToKey[$tr->app_text_id] ?? null;
                if ($key !== null && ($tr->text ?? '') !== '') {
                    $map[$key] = $tr->text;
                }
            }

            // Fill missing translations with the key itself
            foreach ($map as $k => $v) {
                $map[$k] = $v ?? $k;
            }
            ksort($map);

            if (!self::store()->write($scope, $locale, $map)) {
                return false;
            }

            self::$memo[self::mkMemo($scope, $locale)] = $map;
        }

        return true;
    }

    /**
     * Resolve available languages from DB or config.
     *
     * @return Collection<int,array{code:string,name:string}>
     */
    public static function getLanguages(): Collection
    {
        $mode = (string)Config::get('simple-translation.use_locales_from', 'config');

        if ($mode === 'database') {
            try {
                return AppLanguage::query()->select(['code', 'name'])->active()->get();
            } catch (Throwable) {
                // Fall back to config
            }
        }

        $override = Config::get('simple-translation.locales.override');
        if (is_array($override) && !empty($override)) {
            return collect(array_map(
                fn($c) => ['code' => $c, 'name' => strtoupper($c)],
                $override
            ));
        }

        return collect((array)Config::get('simple-translation.config_locales', [
            ['code' => 'en', 'name' => 'English'],
        ]));
    }

    /**
     * Import file store -> DB for given scope and locales.
     * If truncate_on_import = true, both tables are truncated once (call with $firstRun=true).
     *
     * @param string $scope
     * @param array|null $locales null => all configured (getLanguages)
     * @param bool $firstRun true -> apply truncate if enabled
     * @return bool
     */
    public static function importScope(string $scope, ?array $locales = null, bool $firstRun = false): bool
    {
        $locales ??= self::getLanguages()->pluck('code')->all();

        // Truncate once if enabled
        if ($firstRun && Config::get('simple-translation.translations.truncate_on_import', false)) {
            try {
                DB::table('app_text_translations')->truncate();
                DB::table('app_texts')->truncate();
            } catch (Throwable) {}
        }

        // For merge mode, we need all keys present in store across locales:
        $allKeys = [];

        foreach ($locales as $locale) {
            $map = self::store()->read($scope, $locale); // [key => value]
            if (empty($map)) {
                continue;
            }
            $allKeys = array_unique(array_merge($allKeys, array_keys($map)));
        }

        // No files found → nothing to import (not an error)
        if (empty($allKeys)) {
            return true;
        }

        // Ensure all keys exist in DB under this scope
        foreach ($allKeys as $key) {
            self::ensureDbKey($key, $scope);
        }

        // Write translations row-by-row
        foreach ($locales as $locale) {
            $map = self::store()->read($scope, $locale);
            if (empty($map)) {
                continue;
            }

            foreach ($map as $key => $value) {
                // Default policy: if empty, store key itself
                $text = ($value === null || $value === '') ? $key : $value;

                // find key id
                $textRow = AppText::query()
                    ->where('scope', $scope)
                    ->where('text', $key)
                    ->first(['id']);

                if (!$textRow) {
                    // should not happen (we ensured above), but safeguard:
                    $textRow = AppText::query()
                        ->updateOrCreate(['scope' => $scope, 'text' => $key]);
                }

                AppTextTranslation::query()->updateOrCreate(
                    ['app_text_id' => $textRow->id, 'lang_code' => $locale],
                    ['text' => $text]
                );
            }

            // Also refresh memo cache for this scope/locale for current request:
            self::$memo[self::mkMemo($scope, $locale)] = $map;
        }

        return true;
    }

    /**
     * Ensure DB row exists for key+scope.
     *
     * @param string $key
     * @param string $scope
     * @return void
     */
    private static function ensureDbKey(string $key, string $scope): void
    {
        AppText::query()->updateOrCreate(['scope' => $scope, 'text' => $key]);
    }

    /**
     * Look up translation in DB.
     *
     * @param string $key
     * @param string $scope
     * @param string $locale
     * @return string|null
     */
    private static function lookupDb(string $key, string $scope, string $locale): ?string
    {
        $text = AppText::query()
            ->where('scope', $scope)
            ->where('text', $key)
            ->first(['id']);

        if (!$text) {
            return null;
        }

        $tr = AppTextTranslation::query()
            ->where('app_text_id', $text->id)
            ->where('lang_code', $locale)
            ->value('text');

        return $tr ?: null;
    }

    /**
     * Resolve configured store driver.
     *
     * @return StoreDriver
     */
    private static function store(): StoreDriver
    {
        return app(StoreDriver::class);
    }

    /**
     * Build memo cache key.
     *
     * @param string $scope
     * @param string $locale
     * @return string
     */
    private static function mkMemo(string $scope, string $locale): string
    {
        return $scope . '|' . $locale;
    }
}