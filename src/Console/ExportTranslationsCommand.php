<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ExportTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:export
        {--scope= : Scope name (default: config("simple-translation.default_scope"))}
        {--driver= : Force export driver (json|php). Overrides config}
        {--path= : Force export path. Overrides config}
        {--force : Export even if config(translations.enabled=false)}';

    protected $description = 'Export existing DB translations to lang files (JSON or PHP)';

    public function handle(): int
    {
        $scope = (string)($this->option('scope') ?: Config::get('simple-translation.default_scope', 'app'));

        // Driver override
        if ($drv = $this->option('driver')) {
            $drv = strtolower($drv);
            if ($drv === 'php') {
                Config::set('simple-translation.translations.driver', TranslationDriver::PHP);
            } else {
                Config::set('simple-translation.translations.driver', TranslationDriver::JSON);
            }
        }

        // Path override
        if ($path = $this->option('path')) {
            Config::set('simple-translation.translations.path', $path);
        }

        $enabled = (bool)Config::get('simple-translation.translations.enabled', false);
        $force = (bool)$this->option('force');

        if (!$enabled && !$force) {
            $this->comment("Export skipped: translations.enabled=false. Use --force to override.");
            return self::SUCCESS;
        }

        $ok = AppLanguageService::generateTranslationsToStore($scope, $force);

        if ($ok) {
            $this->info("Translations exported successfully for scope '{$scope}'.");
            return self::SUCCESS;
        }

        $this->error("Export failed for scope '{$scope}'.");
        return self::FAILURE;
    }
}