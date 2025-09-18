<?php

namespace Aslnbxrz\SimpleTranslation\Console;

use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Finder\Finder;
use Throwable;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'simple-translation:scan
        {--paths= : Comma-separated dirs to scan (default: app,resources)}
        {--ext= : Comma-separated extensions (php,blade.php,vue,js,ts)}
        {--scope= : (e.g. app|admin)}
        {--dry : Dry-run (do not write to DB)}
        {--no-progress : Hide progress bar}
        {--exclude= : Comma-separated dirs to exclude (vendor,node_modules,storage)}';

    protected $description = 'Scan project for translation calls and persist keys to database';

    /** Regex patterns to extract translation keys */
    private array $patterns = [
        // __('key') or __("key", ...)
        '/__\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        // @lang('key') or @lang("key")
        '/@lang\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        // trans('key')
        '/\btrans\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/u',
        // trans_choice('key', ...)
        '/\btrans_choice\(\s*[\'"]([^\'"]+)[\'"]\s*,/u',
    ];

    public function handle(): int
    {
        // 1) Options & defaults
        $paths = $this->csvOption('paths', ['app', 'resources']);
        $exts = $this->csvOption('ext', ['php', 'blade.php', 'vue', 'js', 'ts']);
        $exclude = $this->csvOption('exclude', ['vendor', 'node_modules', 'storage']);
        $dry = (bool)$this->option('dry');
        $noProg = (bool)$this->option('no-progress');

        // 2) Determine "scope" value stored in AppText->scope
        $scope = (string)($this->option('scope') ?: Config::get('simple-translation.default_scope', 'app'));

        // 3) Build finder
        $finder = new Finder();
        $finder->files();

        foreach ($paths as $p) {
            if (is_dir(base_path($p))) {
                $finder->in(base_path($p));
            }
        }

        foreach ($exclude as $ex) {
            $finder->exclude($ex);
        }

        // Extensions filter (supports blade.php)
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
            $this->info('Nothing to scan. Check --paths / --ext / --exclude options.');
            return self::SUCCESS;
        }

        // 4) Scan
        $keys = [];
        $files = iterator_to_array($finder->getIterator());
        $countFiles = count($files);

        $bar = null;
        if (!$noProg) {
            $bar = $this->output->createProgressBar($countFiles);
            $bar->start();
        }

        foreach ($files as $file) {
            try {
                $content = @file_get_contents($file->getRealPath());
                if ($content === false || $content === '') {
                    if ($bar) $bar->advance();
                    continue;
                }

                foreach ($this->patterns as $rx) {
                    if (preg_match_all($rx, $content, $m)) {
                        foreach ($m[1] as $key) {
                            // Trim and normalize whitespace
                            $k = trim($key);
                            if ($k !== '') {
                                $keys[$k] = true; // dedupe
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Skip unreadable file
            } finally {
                if ($bar) $bar->advance();
            }
        }

        if ($bar) {
            $bar->finish();
            $this->newLine();
        }

        $keys = array_keys($keys);
        $this->info('Files scanned: ' . $countFiles);
        $this->info('Keys found: ' . count($keys));

        if ($dry || empty($keys)) {
            if ($dry) $this->comment('Dry-run: no DB writes performed.');
            return self::SUCCESS;
        }

        // 5) Persist to DB (upsert-like via updateOrCreate)
        $inserted = 0;
        $skipped = 0;

        foreach ($keys as $text) {
            // AppLanguageService::save() sizda bor: updateOrCreate(['scope'=>$scope,'text'=>$text])
            $exists = AppText::query()->where('scope', $scope)->where('text', $text)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            AppLanguageService::save($text, $scope);
            $inserted++;
        }

        $this->info("Inserted: {$inserted}, Skipped (exists): {$skipped}");

        return self::SUCCESS;
    }

    /** Parse comma-separated option into trimmed array */
    private function csvOption(string $name, array $default): array
    {
        $val = (string)($this->option($name) ?? '');
        if ($val === '') return $default;
        return array_values(array_filter(array_map('trim', explode(',', $val)), fn($v) => $v !== ''));
    }
}