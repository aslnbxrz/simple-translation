<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Finder\Finder;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:scan
        {--paths= : CSV dirs to scan (default: app,resources)}
        {--ext= : CSV extensions (php,blade.php,vue,js,ts)}
        {--exclude= : CSV dirs to exclude (vendor,node_modules,storage)}
        {--scope= : Scope to store the found keys under}
        {--dry : Dry-run (no DB writes)}
        {--no-progress : Hide progress bar}';

    protected $description = 'Scan project for translation calls and persist keys to DB under a single scope.';

    /** Patterns for extracting keys. */
    private array $patterns = [
        '/__\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        '/@lang\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        '/\btrans\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        '/\btrans_choice\(\s*[\'"]([^\'"]+)[\'"]\s*,/u',
        '/___\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
    ];

    public function handle(): int
    {
        $paths = $this->csv('paths', ['app', 'resources']);
        $exts = $this->csv('ext', ['php', 'blade.php', 'vue', 'js', 'ts']);
        $exclude = $this->csv('exclude', ['vendor', 'node_modules', 'storage']);
        $dry = (bool)$this->option('dry');
        $noProg = (bool)$this->option('no-progress');

        $scope = (string)($this->option('scope') ?: Config::get('simple-translation.default_scope', 'app'));

        $finder = new Finder();
        $finder->files();

        foreach ($paths as $p) {
            if (is_dir(base_path($p))) $finder->in(base_path($p));
        }
        foreach ($exclude as $ex) $finder->exclude($ex);

        $finder->filter(function (\SplFileInfo $file) use ($exts) {
            $name = $file->getFilename();
            foreach ($exts as $ext) {
                if ($ext === 'blade.php') {
                    if (str_ends_with($name, '.blade.php')) return true;
                } else {
                    if (str_ends_with($name, '.' . $ext)) return true;
                }
            }
            return false;
        });

        if (!$finder->hasResults()) {
            $this->info('Nothing to scan.');
            return self::SUCCESS;
        }

        $keys = [];
        $files = iterator_to_array($finder->getIterator());
        $bar = null;
        if (!$noProg) {
            $bar = $this->output->createProgressBar(count($files));
            $bar->start();
        }

        foreach ($files as $file) {
            $content = @file_get_contents($file->getRealPath());
            if (!$content) {
                $bar?->advance();
                continue;
            }

            foreach ($this->patterns as $rx) {
                if (preg_match_all($rx, $content, $m)) {
                    foreach ($m[1] as $key) {
                        $k = trim($key);
                        if ($k !== '') $keys[$k] = true;
                    }
                }
            }
            $bar?->advance();
        }

        $bar?->finish();
        $this->newLine();
        $keys = array_keys($keys);

        $this->info('Files scanned: ' . count($files));
        $this->info('Keys found: ' . count($keys));

        if ($dry || empty($keys)) {
            if ($dry) $this->comment('Dry-run: no DB writes.');
            return self::SUCCESS;
        }

        $inserted = 0;
        $skipped = 0;
        foreach ($keys as $text) {
            $exists = AppText::query()->where('scope', $scope)->where('text', $text)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            AppLanguageService::translate($text, $scope); // ensures DB key + writes file with default
            $inserted++;
        }

        $this->info("Inserted: {$inserted}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function csv(string $name, array $default): array
    {
        $val = (string)($this->option($name) ?? '');
        if ($val === '') return $default;
        return array_values(array_filter(array_map('trim', explode(',', $val))));
    }
}