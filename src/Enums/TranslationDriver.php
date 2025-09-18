<?php

namespace Aslnbxrz\SimpleTranslation\Enums;

use Illuminate\Support\Facades\Config;

enum TranslationDriver: string
{
    case JSON = 'json';
    case PHP = 'php';

    /**
     * Resolve the base directory where translations should be stored.
     * - If config path is null, fallback to lang_path()
     * - If relative string, resolve via base_path()
     * - If absolute path, return as-is
     */
    public function basePath(): string
    {
        $cfg = Config::get('simple-translation.translations.path');

        if ($cfg === null || $cfg === '') {
            // Use Laravel's default resource/lang path
            return \function_exists('lang_path') ? lang_path() : base_path('resources/lang');
        }

        // Absolute path?
        if (\preg_match('/^(\/|[A-Za-z]:\\\\)/', $cfg) === 1) {
            return $cfg;
        }

        // Treat as relative to project root
        return base_path($cfg);
    }

    /**
     * Build the full destination file path for a given locale.
     */
    public function filePath($language, string $for): string
    {
        $base = rtrim($this->basePath(), DIRECTORY_SEPARATOR);

        return match ($this) {
            self::JSON => $base . DIRECTORY_SEPARATOR . $language['code'] . '.json',
            self::PHP => $base . DIRECTORY_SEPARATOR . $language['code'] . DIRECTORY_SEPARATOR . $this->phpFileName() . '.php',
        };
    }

    /**
     * Encode the data array into the appropriate file contents.
     * Returns null on failure.
     */
    public function encode(array $data): ?string
    {
        return match ($this) {
            self::JSON => self::encodeJson($data),
            self::PHP => self::encodePhp($data),
        };
    }

    private static function encodeJson(array $data): ?string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private static function encodePhp(array $data): string
    {
        $export = var_export($data, true);
        // Convert array() to short syntax []
        $export = preg_replace('/^array \(/', '[', $export);
        $export = preg_replace('/\)$/m', ']', $export);

        return <<<PHP
<?php

return {$export};

PHP;
    }

    private function phpFileName(): string
    {
        $name = Config::get('simple-translation.translations.php_file_name', 'translations');
        return \is_string($name) && $name !== '' ? $name : 'translations';
    }
}
