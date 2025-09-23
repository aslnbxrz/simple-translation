<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Illuminate\Console\Command;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:sync
        {--paths= : CSV dirs to scan (default: app,resources)}
        {--ext= : CSV extensions (php,blade.php,vue,js,ts)}
        {--exclude= : CSV dirs to exclude (vendor,node_modules,storage)}
        {--scope= : Scope(s). CSV allows multiple for export stage}
        {--all : Export all scopes from config}
        {--locales= : CSV locale filter for export}
        {--dry : Scan only (no DB writes, no export)}
        {--no-progress : Hide progress bar}';

    protected $description = 'Scan then export. Scan writes keys into one scope; export writes files for provided/all scopes.';

    public function handle(): int
    {
        // 1) Scan (single scope for DB insert)
        $scan = array_filter([
            '--paths' => $this->option('paths'),
            '--ext' => $this->option('ext'),
            '--exclude' => $this->option('exclude'),
            '--scope' => $this->option('scope'),
            '--dry' => $this->option('dry') ? true : null,
            '--no-progress' => $this->option('no-progress') ? true : null,
        ], fn($v) => $v !== null);

        $this->line('<info>→ Scanning…</info>');
        $rc = $this->call('simple-translation:scan', $scan);
        if ($rc !== self::SUCCESS) return $rc;

        if ($this->option('dry')) {
            $this->comment('Dry-run: export skipped.');
            return self::SUCCESS;
        }

        // 2) Export (CSV scopes or --all)
        $export = array_filter([
            '--scope' => $this->option('scope'),
            '--all' => $this->option('all') ? true : null,
            '--locales' => $this->option('locales'),
        ], fn($v) => $v !== null);

        $this->line('<info>→ Exporting…</info>');
        $rc = $this->call('simple-translation:export', $export);
        if ($rc !== self::SUCCESS) return $rc;

        $this->info('Sync completed.');
        return self::SUCCESS;
    }
}