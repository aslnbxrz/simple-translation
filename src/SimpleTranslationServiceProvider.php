<?php

namespace Aslnbxrz\SimpleTranslation;

use Aslnbxrz\SimpleTranslation\Console\ExportTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Console\ScanTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Console\SyncTranslationsCommand;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Support\ServiceProvider;

class SimpleTranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/simple-translation.php',
            'simple-translation'
        );
    }

    public function boot(): void
    {
        // Load helpers (fallback if not composer "files")
        $helpers = __DIR__ . '/Support/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        // Config publish
        $this->publishes([
            __DIR__ . '/../config/simple-translation.php' => config_path('simple-translation.php'),
        ], 'simple-translation');

        $this->publishes([
            __DIR__ . '/../config/simple-translation.php' => config_path('simple-translation.php'),
        ], 'simple-translation-config');


        // Migration publish
        $this->publishes([
            __DIR__ . '/../database/migrations/0001_01_01_000003_create_simple_translations_table.php'
            => database_path('migrations/0001_01_01_000003_create_simple_translations_table.php'),
        ], 'simple-translation-migration-texts');

        if (AppLanguageService::usingDatabase()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/0001_01_01_000004_create_app_languages_table.php'
                => database_path('migrations/0001_01_01_000004_create_app_languages_table.php'),
            ], 'simple-translation-migration-languages');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanTranslationsCommand::class,
                ExportTranslationsCommand::class,
                SyncTranslationsCommand::class
            ]);
        }
    }
}