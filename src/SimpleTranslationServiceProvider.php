<?php

namespace Aslnbxrz\SimpleTranslation;

use Aslnbxrz\SimpleTranslation\Console\ExportTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Console\ImportTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Console\ScanTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Console\SyncTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Stores\Contracts\StoreDriver;
use Aslnbxrz\SimpleTranslation\Stores\JsonPerScopeStore;
use Aslnbxrz\SimpleTranslation\Stores\PhpArrayPerScopeStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SimpleTranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/simple-translation.php', 'simple-translation');

        // Bind store driver (runtime + export)
        $this->app->singleton(StoreDriver::class, function () {
            $driver = (string)Config::get('simple-translation.translations.driver', 'json-per-scope');
            return match ($driver) {
                'php-array-per-scope' => new PhpArrayPerScopeStore(),
                default => new JsonPerScopeStore(),
            };
        });
    }

    public function boot(): void
    {
        // Load helpers (in case composer "files" not yet loaded)
        $helpers = __DIR__ . '/Support/helpers.php';
        if (is_file($helpers)) require_once $helpers;

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/simple-translation.php' => config_path('simple-translation.php'),
        ], 'simple-translation-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/0001_01_01_000003_create_simple_translations_table.php'
            => database_path('migrations/0001_01_01_000003_create_simple_translations_table.php'),
            __DIR__ . '/../database/migrations/0001_01_01_000004_create_app_languages_table.php'
            => database_path('migrations/0001_01_01_000004_create_app_languages_table.php'),
        ], 'simple-translation-migrations');

        // Seeder publish
        $this->publishes([
            __DIR__ . '/../database/seeders/SimpleTranslationSeeder.php'
            => database_path('seeders/SimpleTranslationSeeder.php'),
        ], 'simple-translation-seeders');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanTranslationsCommand::class,
                ExportTranslationsCommand::class,
                ImportTranslationsCommand::class,
                SyncTranslationsCommand::class,
            ]);
        }
    }
}