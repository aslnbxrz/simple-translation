<?php

use Aslnbxrz\SimpleTranslation\Enums\CacheDriver;
use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Enums\UseLocalesFrom;

return [
    'default_scope' => 'app',
    'use_locales_from' => UseLocalesFrom::Config,
    'locales' => [
        // use only when use_locales_from => UseLocalesFrom::Config
        [
            'code' => 'en',
            'name' => 'English',
        ],
        // add more locales like above
    ],

    'translations' => [
        'enabled' => false,
        'driver' => TranslationDriver::JSON,
        'path' => null, // null for default laravel lang_path()
        'php_file_name' => 'simple_translations',
    ],

    'cache' => [
        'enabled' => true,
        'driver' => CacheDriver::InMemory,
        'ttl' => 300,
        'prefix' => 'simple_translation',
    ],
];