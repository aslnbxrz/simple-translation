<?php

return [
    // Default scope used when none is provided.
    'default_scope' => 'app',

    // Scopes registry: used by --all and select options.
    'available_scopes' => [
        'app' => 'App',
        'admin' => 'Admin',
        'exceptions' => 'Exceptions',
    ],

    // Where to resolve available locales from: "config" or "database".
    'use_locales_from' => 'config',

    // Locales for "config" mode.
    'config_locales' => [
        ['code' => 'en', 'name' => 'English'],
    ],

    // Runtime store driver (also used by export). Per-scope files only.
    'translations' => [
        'driver' => 'json-per-scope', // json-per-scope | php-array-per-scope
        'drivers' => [
            // storage/lang/json/{locale}/{scope}.json
            'json-per-scope' => [
                'base_dir' => lang_path(),
                'pretty'   => false, // pretty print json
                'lock'     => true,  // LOCK_EX on write
            ],
            // lang/vendor/simple-translation/{locale}/{scope}.php
            'php-array-per-scope' => [
                'base_dir' => lang_path('vendor/simple-translation'),
                'lock'     => true,  // LOCK_EX on write
            ],
        ],
    ],
];