# Simple Translation

*A lightweight, production-ready translation manager for Laravel.*

Simple Translation lets you **scan** your codebase for translation keys, **store** them in your database, and **export**
them to Laravel-native language files (JSON or PHP). It ships with a fast `___()` helper (like Laravel's `__()`), smart
caching (in-memory + Redis), and handy Artisan commands (`scan`, `export`, `sync`) to keep your translations in sync.

---

## 🧭 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Migrations](#-migrations)
- [Configuration](#-configuration)
- [Quick Start](#-quick-start)
- [Helper `___()`](#-helper-___)
- [Commands](#-commands)
    - [Scan](#scan)
    - [Export](#export)
    - [Sync](#sync)
- [Exporting to Files](#-exporting-to-files)
- [Caching (InMemory & Redis)](#-caching-inmemory--redis)
- [Performance & Scaling](#-performance--scaling)
- [Eloquent Models & Schema](#-eloquent-models--schema)
- [Testing (Orchestra Testbench)](#-testing-orchestra-testbench)
- [Troubleshooting](#-troubleshooting)
- [FAQ](#-faq)
- [Versioning & Compatibility](#-versioning--compatibility)
- [Security](#-security)
- [License](#-license)
- [Contributing](#-contributing)

---

## ✨ Features

- **Scan & Sync**: Parse `app/`, `resources/`, `*.blade.php`, `*.php`, `*.vue`, `*.js`, `*.ts` for translation calls and
  persist unique keys.
- **DB-backed translations**: Store keys and translations in `app_texts`, `app_text_translations`, manage languages in
  `app_languages`.
- **File Export**: Generate JSON (`en.json`) or PHP (`resources/lang/en/simple_translations.php`) files on demand.
- **`___()` Helper**: Auto-save missing keys and return translated values with minimal I/O.
- **Scopes**: Organize by scope (e.g., `app`, `admin`, `emails`).
- **Caching**: In-request memo + optional Redis cache per `scope+locale` for blazing fast lookups.
- **Zero-config Autodiscovery**: Laravel auto-discovers the service provider.
- **Three Artisan Commands**: `scan`, `export`, and `sync`.

---

## ✅ Requirements

- **PHP**: ^8.1
- **Laravel**: 10.x, 11.x (Orchestra Testbench covered in tests)
- **Database**: SQLite/MySQL/PostgreSQL (package-agnostic). SQLite is fine for testing.
- **Redis** (optional): for cross-request caching.

---

## 📦 Installation

```bash
composer require aslnbxrz/simple-translation
```

Publish config:

```bash
php artisan vendor:publish --tag=simple-translation-config
```

Publish migrations (separate tags):

```bash
# app_languages table
php artisan vendor:publish --tag=simple-translation-migration-languages

# app_texts & app_text_translations tables
php artisan vendor:publish --tag=simple-translation-migration-texts
```

Run migrations:

```bash
php artisan migrate
```

---

## ⚙️ Configuration

`config/simple-translation.php`:

```php
use Aslnbxrz\SimpleTranslation\Enums\CacheDriver;
use Aslnbxrz\SimpleTranslation\Enums\TranslationDriver;
use Aslnbxrz\SimpleTranslation\Enums\UseLocalesFrom;

return [
    'default_scope' => 'app',

    'use_locales_from' => UseLocalesFrom::Database, // or Config

    'locales' => [
        ['code' => 'en', 'name' => 'English'],
    ],

    'translations' => [
        'enabled' => false,
        'driver' => TranslationDriver::JSON, // or PHP
        'path' => null,
        'php_file_name' => 'simple_translations',
    ],

    'cache' => [
        'enabled' => true,
        'driver' => CacheDriver::InMemory, // InMemory | Redis
        'ttl' => 300,
        'prefix' => 'simple_translation',
    ],
];
```

---

## 🚀 Quick Start

```php
use Aslnbxrz\SimpleTranslation\Models\AppLanguage;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;

// 1) Seed languages
AppLanguage::updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
AppLanguage::updateOrCreate(['code' => 'ru'], ['name' => 'Русский', 'is_active' => true]);

// 2) Save a key and translate
$text = AppLanguageService::save('Welcome to our site');
AppLanguageService::translate($text, 'ru', 'Добро пожаловать на сайт');

// 3) Fetch translations
app()->setLocale('ru');
echo ___('Welcome to our site'); // → Добро пожаловать на сайт
```

---

## 🧰 Helper `___()`

The `___()` helper behaves like Laravel’s `__()` but **auto-saves** missing keys to DB and returns their translation.

```blade
{{ ___('Dashboard') }}               // default scope = app
{{ ___('Users', 'admin') }}          // custom scope
```

How it works:

- Checks an in-request memo (fastest).
- Loads cached list (in-memory or Redis).
- If key is missing, saves to DB and refreshes the cache.

---

## 🛠 Commands

### Scan

Extract keys from source files and persist them to DB:

```bash
php artisan simple-translation:scan --paths=app,resources --scope=app
```

Options:

- `--paths` directories (default: `app,resources`)
- `--ext` extensions (`php,blade.php,vue,js,ts`)
- `--scope` assign scope
- `--dry` dry-run (no DB writes)
- `--exclude` directories to skip

### Export

Export existing DB translations to files:

```bash
php artisan simple-translation:export --scope=app
```

Options:

- `--scope` scope name
- `--driver` force driver (`json|php`)
- `--path` custom export path
- `--force` run even if `translations.enabled=false`

### Sync

Run **scan + export** in one step:

```bash
php artisan simple-translation:sync --scope=app
```

Options (combines scan + export):

- `--dry` only scan (skip export)
- `--driver` override driver (`json|php`)
- `--path` export path
- `--export` force export even if disabled

**When to use `sync`:**

- **Deploy pipelines**: update DB keys and regenerate lang files in one command.
- **Initial setup**: populate DB from code and immediately export.
- **Everyday dev**: usually only `scan` is needed; `sync` is handy if you want files updated instantly.

---

## 📤 Exporting to Files

When `translations.enabled = true`, files are auto-generated on `save/translate/delete`. You can also trigger manually
via `export` or `sync`.

- **JSON**: `resources/lang/en.json`, `resources/lang/ru.json`
- **PHP**: `resources/lang/en/simple_translations.php`

---

## ⚡ Caching (InMemory & Redis)

- **In-request memo**: per-request, always on.
- **Driver cache**: `InMemory` (simple), `Redis` (cross-request, per `scope+locale`).
- Cache invalidation on `save`, `translate`, `delete`.

---

## 📈 Performance & Scaling

- Use **scopes** to keep lists small.
- Use **Redis** in production.
- First request hydrates cache from DB, subsequent calls are O(1).

---

## 🧱 Eloquent Models & Schema

- `app_languages` (code, name, active)
- `app_texts` (scope + text)
- `app_text_translations` (app_text_id + lang_code + text)

Cascade delete ensures translations are removed with their parent text.

---

## 🧪 Testing (Orchestra Testbench)

```bash
composer install
composer test
```

Uses SQLite in-memory by default. MySQL/PostgreSQL supported.

---

## 🛠 Troubleshooting

- **`no such table: app_languages`** → publish & run migrations.
- **Redis cache not used** → check `config/simple-translation.php` and `CACHE_STORE=redis`.
- **Helper returns key** → add translation for that locale & scope.

---

## ❓ FAQ

- **How is `___()` different from Laravel `__()`?** → `___()` auto-saves missing keys.
- **Can I use config locales instead of DB?** → Yes, set `use_locales_from=Config`.
- **Can I disable file generation?** → Yes, set `translations.enabled=false`.

---

## 🧭 Versioning & Compatibility

- Semantic Versioning
- Laravel 10/11, PHP ^8.1

---

## 🔐 Security

Report security issues privately to **bexruz.aslonov1@gmail.com**.

---

## 📄 License

MIT License.

---

## 🤝 Contributing

PRs/issues welcome. Please include tests:

```bash
composer test
```

---

**Made with ❤️ by Bexruz (aslnbxrz)**