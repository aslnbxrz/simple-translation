<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ImportTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:import
        {--scope= : CSV scopes (e.g. app,admin). Omit with --all}
        {--all : Import all scopes from config("simple-translation.available_scopes")}
        {--locales= : CSV locales filter (e.g. en,uz). Omit = all configured}
        {--truncate : Force truncate before import (overrides config)}
    ';

    protected $description = 'Import per-scope files into DB (merge or truncate+fill).';

    public function handle(): int
    {
        // Determine scopes
        $scopes = $this->csv('scope');
        if ($this->option('all') || empty($scopes)) {
            $scopes = array_keys((array)config('simple-translation.available_scopes', []));
            if (empty($scopes)) {
                $scopes = [(string)config('simple-translation.default_scope', 'app')];
            }
        }

        // Limit locales for this run (temporary override)
        $locales = $this->csv('locales');
        if (!empty($locales)) {
            Config::set('simple-translation.locales.override', $locales);
        }

        $firstRun = true;
        $okAll = true;

        // Override truncate flag from CLI if provided
        if ($this->option('truncate')) {
            Config::set('simple-translation.translations.truncate_on_import', true);
        }

        foreach ($scopes as $scope) {
            $ok = AppLanguageService::importScope($scope, !empty($locales) ? $locales : null, $firstRun);
            $this->line($ok ? "Imported: {$scope}" : "Failed: {$scope}");
            $okAll = $okAll && $ok;
            $firstRun = false; // truncate (if any) only once
        }

        // Clear override
        if (!empty($locales)) {
            Config::offsetUnset('simple-translation.locales.override');
        }

        if ($okAll) {
            $this->info('Translations imported successfully.');
            return self::SUCCESS;
        }

        $this->error('Some scopes failed to import.');
        return self::FAILURE;
    }

    /** @return array<int,string> */
    private function csv(string $name): array
    {
        $raw = (string)($this->option($name) ?? '');
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}