<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ExportTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:export
        {--scope= : CSV scopes (e.g. app,exceptions). Omit with --all}
        {--all : Export all scopes from config("simple-translation.available_scopes")}
        {--locales= : CSV locales filter (e.g. en,uz). Omit = all configured}';

    protected $description = 'Export DB translations into per-scope files (does not touch other scopes).';

    public function handle(): int
    {
        // Determine scopes
        $scopes = $this->csv('scope');
        if ($this->option('all') || empty($scopes)) {
            $scopes = array_keys((array) config('simple-translation.available_scopes', []));
            if (empty($scopes)) {
                $scopes = [(string) config('simple-translation.default_scope', 'app')];
            }
        }

        // Limit locales for this run (temporary override)
        $locales = $this->csv('locales');
        if (!empty($locales)) {
            Config::set('simple-translation.locales.override', $locales);
        }

        $okAll = true;
        foreach ($scopes as $scope) {
            $ok = AppLanguageService::exportScope($scope);
            $this->line($ok ? "Exported: {$scope}" : "Failed: {$scope}");
            $okAll = $okAll && $ok;
        }

        // Clear override
        if (!empty($locales)) {
            Config::offsetUnset('simple-translation.locales.override');
        }

        if ($okAll) {
            $this->info('Translations exported successfully.');
            return self::SUCCESS;
        }

        $this->error('Some scopes failed to export.');
        return self::FAILURE;
    }

    /** @return array<int,string> */
    private function csv(string $name): array
    {
        $raw = (string) ($this->option($name) ?? '');
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}