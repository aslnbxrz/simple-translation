<?php

namespace Aslnbxrz\SimpleTranslation\Stores;

use Aslnbxrz\SimpleTranslation\Stores\Contracts\StoreDriver;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * JSON-per-scope store:
 *
 * Layout:
 *   {base_dir}/{locale}/{scope}.json
 *
 * Guards:
 * - Writes only for allowed locales (from config override or config_locales).
 * - Sanitizes locale/scope to avoid unexpected folders or paths.
 * - Optional pretty output and atomic writes (LOCK_EX) via config.
 */
class JsonPerScopeStore implements StoreDriver
{
    /**
     * Read map for a scope+locale.
     * Returns [] if file missing or locale not allowed.
     */
    public function read(string $scope, string $locale): array
    {
        $locale = $this->normalizeLocale($locale);
        if (!$this->isAllowedLocale($locale)) {
            return [];
        }

        $file = $this->path($scope, $locale);
        if (!is_file($file)) {
            return [];
        }

        try {
            $json = file_get_contents($file);
            $arr = json_decode($json ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            return is_array($arr) ? $arr : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Overwrite full map for a scope+locale.
     * Returns false if locale is not allowed or write fails.
     */
    public function write(string $scope, string $locale, array $map): bool
    {
        $locale = $this->normalizeLocale($locale);
        $scope = $this->normalizeScope($scope);

        if (!$this->isAllowedLocale($locale)) {
            return false;
        }

        $file = $this->path($scope, $locale);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $cfg = (array)Config::get('simple-translation.translations.drivers.json-per-scope', []);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (!empty($cfg['pretty'])) {
            $flags |= JSON_PRETTY_PRINT;
        }

        ksort($map);
        $payload = json_encode($map, $flags);
        if ($payload === false) {
            return false;
        }

        $lock = !empty($cfg['lock']);
        return file_put_contents($file, $payload, $lock ? LOCK_EX : 0) !== false;
    }

    /**
     * Merge single key=>value into the file (create file if missing).
     * Returns false if locale not allowed or persisted write fails.
     */
    public function upsert(string $scope, string $locale, string $key, string $value): bool
    {
        $locale = $this->normalizeLocale($locale);
        $scope = $this->normalizeScope($scope);

        if (!$this->isAllowedLocale($locale)) {
            return false;
        }

        $map = $this->read($scope, $locale);
        if (($map[$key] ?? null) === $value) {
            return true; // nothing to do
        }

        $map[$key] = $value;
        return $this->write($scope, $locale, $map);
    }

    /**
     * Absolute path for scope+locale file.
     *
     * Example:
     *   base_dir/en/app.json
     */
    public function path(string $scope, string $locale): string
    {
        $base = (string)Config::get('simple-translation.translations.drivers.json-per-scope.base_dir', lang_path());
        $locale = $this->normalizeLocale($locale);
        $scope = $this->normalizeScope($scope);

        return rtrim($base, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $locale
            . DIRECTORY_SEPARATOR . $scope . '.json';
    }

    /**
     * Return normalized locale: lowercase and trimmed to [A-Za-z0-9_-]+ only.
     */
    private function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);
        $locale = preg_replace('/[^A-Za-z0-9_-]/', '', $locale) ?? '';
        return strtolower($locale);
    }

    /**
     * Return normalized scope: trimmed to [A-Za-z0-9_.-]+ only.
     * (allow dot for scopes like "backoffice.v2" if you ever want that)
     */
    private function normalizeScope(string $scope): string
    {
        $scope = trim($scope);
        $scope = preg_replace('/[^A-Za-z0-9_.-]/', '', $scope) ?? '';
        return $scope;
    }

    /**
     * Allowed locales are taken from:
     * - config('simple-translation.locales.override') if present
     * - otherwise config('simple-translation.config_locales')
     *
     * If the list is empty (misconfiguration), we consider *no* locales allowed.
     */
    private function isAllowedLocale(string $locale): bool
    {
        $override = Config::get('simple-translation.locales.override');
        if (is_array($override) && !empty($override)) {
            $codes = array_map(fn($c) => strtolower((string)$c), $override);
            return in_array($locale, $codes, true);
        }

        $cfg = (array)Config::get('simple-translation.config_locales', []);
        if (empty($cfg)) {
            return false;
        }

        $codes = array_map(
            fn($row) => strtolower((string)($row['code'] ?? '')),
            $cfg
        );

        return in_array($locale, $codes, true);
    }
}