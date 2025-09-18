<?php

namespace Aslnbxrz\SimpleTranslation\Services;

use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Enums\UseLocalesFrom;
use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Models\AppTextTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class AppLanguageService
{
    public static function getTranslatedList(?string $scope = null, ?string $locale = null): array
    {
        $scope ??= self::getDefaultScope();
        $locale = $locale ?: App::getLocale();

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
    }

    public static function save(string $text, ?string $scope = null): AppText
    {
        $scope ??= self::getDefaultScope();
        $appText = AppText::query()->updateOrCreate(['scope' => $scope, 'text' => $text]);
        self::generateTranslationsToStore($scope);
        return $appText;
    }

    public static function translate(AppText $appText, string $langCode, string $translation): void
    {
        $appText->translate($langCode, $translation);
        self::generateTranslationsToStore($appText->scope);
    }

    public static function delete(AppText $appText): ?bool
    {
        return $appText->delete();
    }

    public static function generateTranslationsToStore(?string $scope = null): bool
    {
        $scope ??= self::getDefaultScope();

        if (!Config::get('simple-translation.translations.enabled', false)) {
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

        // Resolve full destination file path using the enum helpers
        $file = $driver->filePath($language, $scope);

        // Ensure directory exists
        $dir = \dirname($file);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0777, true, true);
        }

        // Encode contents according to driver
        $contents = $driver->encode($data);

        if ($contents === null) {
            return false;
        }

        // Write atomically
        $written = File::put($file, $contents, LOCK_EX);

        return $written !== false;
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
        $val = Config::get('simple-translation.use_locales_from', UseLocalesFrom::Database);
        return $val instanceof UseLocalesFrom ? $val : UseLocalesFrom::from((string)$val);
    }

    private static function getDriver(): TranslationDriver
    {
        $val = Config::get('simple-translation.translations.driver', TranslationDriver::JSON);
        return $val instanceof TranslationDriver ? $val : TranslationDriver::from((string)$val);
    }

    private static function getDefaultScope(): string
    {
        return Config::get('simple-translation.default_scope', 'app');
    }
}
