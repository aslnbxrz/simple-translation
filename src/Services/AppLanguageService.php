<?php

namespace Aslnbxrz\SimpleTranslation\Services;

use Aslnbxrz\SimpleTranslation\Enums\CacheDriver;
use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Enums\UseLocalesFrom;
use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Models\AppTextTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class AppLanguageService
{
    private static array $inRequest = [];

    public static function getTranslatedList(?string $scope = null, ?string $locale = null): array
    {
        $scope ??= self::getDefaultScope();
        $locale = $locale ?: App::getLocale();

        $inKey = $scope . '|' . $locale;
        if (isset(self::$inRequest[$inKey])) {
            return self::$inRequest[$inKey];
        }

        $useCache = self::cacheEnabled();
        $cacheDriver = self::getCacheDriver();
        $cachePrefix = self::getCachePrefix();
        $cacheTtl = self::getCacheTtl();

        $loader = function () use ($scope, $locale): array {
            /** @var Collection<int, AppText> $texts */
            $texts = AppText::query()->where('scope', $scope)->get(['id', 'text']);

            if ($texts->isEmpty()) {
                return [];
            }

            $translations = AppTextTranslation::query()
                ->where('lang_code', $locale)
                ->whereIn('app_text_id', $texts->pluck('id'))
                ->pluck('text', 'app_text_id');

            $result = [];
            foreach ($texts as $text) {
                $result[$text->text] = $translations[$text->id] ?? $text->text;
            }
            return $result;
        };

        // Default: InMemory
        if ($useCache && $cacheDriver === CacheDriver::Redis) {
            // cross-request cache with Redis
            $store = $cacheDriver->value; // null => default cache store
            $key = self::redisKeyList($cachePrefix, $scope, $locale);

            $data = Cache::store($store)->remember($key, $cacheTtl, $loader);
        } else {
            // InMemory or cache disabled
            $data = $loader();
        }

        // write to in-request cache for both cases
        return self::$inRequest[$inKey] = $data;
    }

    public static function save(string $text, ?string $scope = null): AppText
    {
        $scope ??= self::getDefaultScope();
        $appText = AppText::query()->updateOrCreate(['scope' => $scope, 'text' => $text]);

        self::generateTranslationsToStore($scope);
        self::flushScopeCaches($scope);

        return $appText;
    }

    public static function translate(AppText $appText, string $langCode, string $translation): void
    {
        $appText->translate($langCode, $translation);

        self::generateTranslationsToStore($appText->scope);
        self::flushScopeCaches($appText->scope, [$langCode]);
    }

    public static function delete(AppText $appText): ?bool
    {
        $scope = $appText->scope;
        $res = $appText->delete();

        self::flushScopeCaches($scope);

        return $res;
    }

    public static function generateTranslationsToStore(?string $scope = null, bool $force = false): bool
    {
        $scope ??= self::getDefaultScope();

        if (!Config::get('simple-translation.translations.enabled', false) && !$force) {
            return true;
        }

        /** @var Collection<int, object{code:string}> $languages */
        $languages = self::getLanguages();
        if ($languages->isEmpty()) {
            return true;
        }
        $languageCodes = $languages->pluck('code')->unique()->values();

        /** @var Collection<int, AppText> $texts */
        $texts = AppText::query()->where('scope', $scope)->get(['id', 'text']);

        if ($texts->isEmpty()) {
            $ok = true;
            foreach ($languages as $language) {
                $ok = self::saveToStore($language, [], $scope) && $ok;
            }
            return $ok;
        }

        $idToKey = $texts->pluck('text', 'id');
        $baseMap = $texts->pluck('text', 'text')->all();

        $translations = AppTextTranslation::query()
            ->whereIn('lang_code', $languageCodes)
            ->whereIn('app_text_id', $texts->pluck('id'))
            ->get(['app_text_id', 'lang_code', 'text']);

        $byLang = $translations->groupBy('lang_code');

        $allOk = true;

        foreach ($languages as $language) {
            $code = $language['code'];

            $data = $baseMap;

            /** @var Collection<int, AppTextTranslation> $items */
            $items = $byLang->get($code, collect());

            foreach ($items as $tr) {
                $key = $idToKey[$tr->app_text_id] ?? null;
                if ($key !== null && $tr->text !== null && $tr->text !== '') {
                    $data[$key] = $tr->text;
                }
            }

            if (!self::saveToStore($language, $data, $scope)) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    private static function saveToStore(array $language, array $data, ?string $scope = null): bool
    {
        $scope ??= self::getDefaultScope();
        $driver = self::getDriver();

        $file = $driver->filePath($language, $scope);

        // Ensure directory exists
        $dir = \dirname($file);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0777, true, true);
        }

        $contents = $driver->encode($data);
        if ($contents === null) {
            return false;
        }

        $written = File::put($file, $contents, LOCK_EX);

        return $written !== false;
    }

    /**
     * Invalidation: clear cross-request cache and redis cache.
     */
    private static function flushScopeCaches(string $scope, ?array $locales = null): void
    {
        // In-request cache
        if ($locales === null) {
            // filter keys by scope
            foreach (array_keys(self::$inRequest) as $k) {
                if (\str_starts_with($k, $scope . '|')) {
                    unset(self::$inRequest[$k]);
                }
            }
        } else {
            foreach ($locales as $code) {
                unset(self::$inRequest[$scope . '|' . $code]);
            }
        }

        // Redis cache clear cross-request cache
        $useCache = self::cacheEnabled();
        $driver = self::getCacheDriver();
        if (!$useCache || $driver !== CacheDriver::Redis) {
            return;
        }

        $store = $driver->value;
        $prefix = self::getCachePrefix();

        $codes = $locales ?: self::getLanguages()->pluck('code')->all();
        foreach ($codes as $code) {
            Cache::store($store)->forget(self::redisKeyList($prefix, $scope, $code));
        }
    }

    private static function redisKeyList(string $prefix, string $scope, string $locale): string
    {
        // namespacing: {{simple_translation_prefix}}:list:{scope}:{locale}
        return rtrim($prefix, ':') . ':list:' . $scope . ':' . $locale;
    }

    public static function getLanguages(): Collection
    {
        return self::getUsingFrom()->getLanguages();
    }

    public static function usingDatabase(): bool
    {
        return self::getUsingFrom() === UseLocalesFrom::Database;
    }

    public static function usingConfig(): bool
    {
        return self::getUsingFrom() === UseLocalesFrom::Config;
    }

    private static function getUsingFrom(): UseLocalesFrom
    {
        return UseLocalesFrom::tryFrom(Config::get('simple-translation.use_locales_from')) ?? UseLocalesFrom::Database;
    }

    private static function getDriver(): TranslationDriver
    {
        return TranslationDriver::tryFrom(Config::get('simple-translation.translations.driver')) ?? TranslationDriver::JSON;
    }

    private static function getDefaultScope(): string
    {
        return Config::get('simple-translation.default_scope', 'app');
    }

    private static function getCacheDriver(): CacheDriver
    {
        return CacheDriver::tryFrom(Config::get('simple-translation.cache.driver')) ?? CacheDriver::InMemory;
    }

    private static function getCacheTtl(): int
    {
        return Config::get('simple-translation.cache.ttl', 300);
    }

    private static function getCachePrefix(): string
    {
        return Config::get('simple-translation.cache.prefix', 'simple_translation');
    }

    private static function cacheEnabled(): bool
    {
        return (bool)Config::get('simple-translation.cache.enabled', true);
    }
}
