<?php

return [
    'default_scope' => 'app',
    'available_scopes' => [
        'app',
        'admin'
    ],
    'use_locales_from' => 'config', // config | database
    'config_locales' => [
        [
            'code' => 'en',
            'name' => 'English',
        ],
    ],

    'translations' => [
        'enabled' => false,
        'driver' => 'json', // json | php
        'path' => null, // null for default laravel lang_path()
        'php_file_name' => 'simple_translations',
    ],

    'cache' => [
        'enabled' => true,
        'driver' => 'in_memory', // in_memory | redis
        'ttl' => 300,
        'prefix' => 'simple_translation',
    ],
];