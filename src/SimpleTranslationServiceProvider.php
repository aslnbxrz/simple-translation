<?php

namespace Aslnbxrz\SimpleTranslation;

use Aslnbxrz\SimpleTranslation\Console\ScanTranslationsCommand;
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
        // Config publish
        $this->publishes([
            __DIR__ . '/../config/simple-translation.php' => config_path('simple-translation.php'),
        ], 'simple-translation');

        $this->publishes([
            __DIR__ . '/../config/simple-translation.php' => config_path('simple-translation.php'),
        ], 'simple-translation-config');

        // Migration load
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Migration publish
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'simple-translation-migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'simple-translation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanTranslationsCommand::class,
            ]);
        }
    }
}