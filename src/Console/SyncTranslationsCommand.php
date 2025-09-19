<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Illuminate\Console\Command;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:sync
        {--paths= : Comma-separated dirs to scan (default: app,resources)}
        {--ext= : Comma-separated extensions (php,blade.php,vue,js,ts)}
        {--scope= : Scope name (e.g. app|admin)}
        {--dry : Scan only (do not write to DB, and skip export)}
        {--no-progress : Hide progress bar}
        {--exclude= : Comma-separated dirs to exclude (vendor,node_modules,storage)}
        {--export : (deprecated) Same as --force; kept for backward-compat}
        {--force : Force export even if translations.enabled=false}
        {--driver= : Export driver override (json|php)}
        {--path= : Export path override}';

    protected $description = 'Scan source code for translation keys, then export DB translations to lang files';

    public function handle(): int
    {
        // 1) Run scan (pass-through options)
        $scanOptions = [
            '--paths' => $this->option('paths'),
            '--ext' => $this->option('ext'),
            '--scope' => $this->option('scope'),
            // flags: only pass when true
            '--dry' => $this->option('dry') ? true : null,
            '--no-progress' => $this->option('no-progress') ? true : null,
            '--exclude' => $this->option('exclude'),
        ];

        $this->line('<info>→ Running scan…</info>');
        $scanCode = $this->call('simple-translation:scan', array_filter($scanOptions, fn($v) => $v !== null));
        if ($scanCode !== self::SUCCESS) {
            $this->error('Scan failed. Aborting sync.');
            return $scanCode;
        }

        // If --dry provided, skip export step
        if ($this->option('dry')) {
            $this->comment('Dry-run requested: export step skipped.');
            return self::SUCCESS;
        }

        // 2) Run export
        // Prefer --force; fallback to legacy --export for BC
        $forceExport = $this->option('force') ?: $this->option('export');

        $exportOptions = [
            '--scope' => $this->option('scope'),
            '--driver' => $this->option('driver'),
            '--path' => $this->option('path'),
            '--force' => $forceExport ? true : null,
        ];

        $this->line('<info>→ Running export…</info>');
        $exportCode = $this->call('simple-translation:export', array_filter($exportOptions, fn($v) => $v !== null));
        if ($exportCode !== self::SUCCESS) {
            $this->error('Export failed.');
            return $exportCode;
        }

        $this->info('Sync completed successfully.');
        return self::SUCCESS;
    }
}