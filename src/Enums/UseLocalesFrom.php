<?php

namespace Aslnbxrz\SimpleTranslation\Enums;

use Aslnbxrz\SimpleTranslation\Models\AppLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

enum UseLocalesFrom: string
{
    case Database = 'database';
    case Config = 'config';

    /**
     * @return Collection<int, object{code:string, name:string}>
     */
    public function getLanguages(): Collection
    {
        $languages = match ($this) {
            self::Database => AppLanguage::query()->select(['code', 'name'])->scopes('active')->get()->toArray(),
            self::Config => Config::get('simple-translation.config_locales'),
        };
        return collect($languages);
    }
}
