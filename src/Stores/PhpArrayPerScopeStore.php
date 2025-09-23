<?php

namespace Aslnbxrz\SimpleTranslation\Stores;

use Aslnbxrz\SimpleTranslation\Stores\Contracts\StoreDriver;
use Illuminate\Support\Facades\Config;

class PhpArrayPerScopeStore implements StoreDriver
{
    public function read(string $scope, string $locale): array
    {
        $file = $this->path($scope, $locale);
        if (!is_file($file)) return [];
        try {
            $data = include $file;
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function write(string $scope, string $locale, array $map): bool
    {
        $file = $this->path($scope, $locale);
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        ksort($map);
        $export = var_export($map, true);
        $export = preg_replace('/^array\s*\(/', '[', $export);
        $export = preg_replace('/\)\s*$/', ']', $export);

        $payload = <<<PHP
<?php

return {$export};

PHP;

        $lock = !empty(Config::get('simple-translation.translations.drivers.php-array-per-scope.lock', true));
        return file_put_contents($file, $payload, $lock ? LOCK_EX : 0) !== false;
    }

    public function upsert(string $scope, string $locale, string $key, string $value): bool
    {
        $map = $this->read($scope, $locale);
        if (($map[$key] ?? null) === $value) return true;

        $map[$key] = $value;
        ksort($map);
        return $this->write($scope, $locale, $map);
    }

    public function path(string $scope, string $locale): string
    {
        $base = (string)Config::get('simple-translation.translations.drivers.php-array-per-scope.base_dir', lang_path('vendor/simple-translation'));
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $scope . '.php';
    }
}