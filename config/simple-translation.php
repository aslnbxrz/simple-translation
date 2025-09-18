<?php

use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Enums\UseLocalesFrom;

return [
    'default_scope' => 'app',
    'use_locales_from' => UseLocalesFrom::Database,
    // use only when use_locales_from => UseLocalesFrom::Config
    'locales' => [
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
    ]
];