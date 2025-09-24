<?php

namespace Database\Seeders;

use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SimpleTranslationSeeder extends Seeder
{
    public function run(): void
    {
        if (!Config::get('simple-translation.translations.restore_on_seed', true)) {
            return;
        }

        // Only restore if empty (so works when DB is fresh)
        $empty = 0 === DB::table('app_texts')->count();

        if (!$empty) {
            return;
        }

        // Scopes to import
        $scopes = array_keys((array)config('simple-translation.available_scopes', []));
        if (empty($scopes)) {
            $scopes = [(string)config('simple-translation.default_scope', 'app')];
        }

        $firstRun = true;

        foreach ($scopes as $scope) {
            AppLanguageService::importScope($scope, null, $firstRun);
            $firstRun = false;
        }
    }
}