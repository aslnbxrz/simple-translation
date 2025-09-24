# Simple Translation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aslnbxrz/simple-translation.svg)](https://packagist.org/packages/aslnbxrz/simple-translation)
[![Total Downloads](https://img.shields.io/packagist/dt/aslnbxrz/simple-translation.svg)](https://packagist.org/packages/aslnbxrz/simple-translation)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

*A lightweight, file-first translation manager for Laravel with database backup.*

Simple Translation uses a **file-first approach** with database backup. It lets you **scan** your codebase for translation keys, **import/export** them between database and files, and **organize** translations by scopes. It ships with a fast `___()` helper (like Laravel's `__()`), per-scope file storage (JSON or PHP), and handy Artisan commands (`scan`, `export`, `import`, `sync`) to keep your translations in sync.

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

- **File-First Strategy**: Prefers translations from per-scope files, with database as backup.
- **Scan & Import/Export**: Parse `app/`, `resources/`, `*.blade.php`, `*.php`, `*.vue`, `*.js`, `*.ts` for translation calls and sync between files and database.
- **Per-Scope Organization**: Store translations in separate files per scope (e.g., `app.json`, `admin.json`).
- **Multiple File Formats**: Support for JSON (`{locale}/{scope}.json`) or PHP array (`{locale}/{scope}.php`) formats.
- **`___()` Helper**: File-first translation with automatic DB fallback and key creation.
- **Scopes Management**: Organize translations by scope (e.g., `app`, `admin`, `exceptions`).
- **In-Memory Caching**: Per-request memoization for blazing fast lookups.
- **Zero-config Autodiscovery**: Laravel auto-discovers the service provider.
- **Four Artisan Commands**: `scan`, `export`, `import`, and `sync`.

---

## ✅ Requirements

- **PHP**: ^8.1
- **Laravel**: ^10.0|^11.0|^12.0
- **Database**: SQLite/MySQL/PostgreSQL (package-agnostic). SQLite is fine for testing.
- **Storage**: File system access for translation files.

---

## 📦 Installation

```bash
composer require aslnbxrz/simple-translation
```

Publish config:

```bash
php artisan vendor:publish --tag=simple-translation-config
```

Publish migrations:

```bash
# All migration files
php artisan vendor:publish --tag=simple-translation-migrations
```

Run migrations:

```bash
php artisan migrate
```

Publish seeder (optional):

```bash
php artisan vendor:publish --tag=simple-translation-seeders
```

---

## ⚙️ Configuration

`config/simple-translation.php`:

```php
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
                'pretty' => false, // pretty print json
                'lock' => true,  // LOCK_EX on write
            ],
            // lang/vendor/simple-translation/{locale}/{scope}.php
            'php-array-per-scope' => [
                'base_dir' => lang_path('vendor/simple-translation'),
                'lock' => true,  // LOCK_EX on write
            ],
        ],

        // Auto restore (import) on seeding:
        // If true, SimpleTranslationSeeder will import translations from files if DB is empty.
        'restore_on_seed' => true,

        // refill DB while importing:
        // true => app_texts va app_text_translations will be truncated
        // false => existing will be preserved, new will be added, missing will be removed (merge)
        'truncate_on_import' => false,
    ],
];
```

---

## 🚀 Quick Start

```php
use Aslnbxrz\SimpleTranslation\Models\AppLanguage;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;

// 1) Seed languages (if using database mode)
AppLanguage::updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
AppLanguage::updateOrCreate(['code' => 'ru'], ['name' => 'Русский', 'is_active' => true]);

// 2) Scan your codebase for translation keys
php artisan simple-translation:scan --scope=app

// 3) Export translations to files
php artisan simple-translation:export --scope=app

// 4) Use the helper in your views
echo ___('Welcome to our site'); // → Reads from files first, DB fallback
```

**File Structure:**
```
lang/
├── en/
│   ├── app.json          # App scope translations
│   └── admin.json        # Admin scope translations
└── ru/
    ├── app.json
    └── admin.json
```

### Seeder Support

The package includes a seeder that can automatically import existing translation files when the database is empty:

```php
// In your DatabaseSeeder
$this->call(SimpleTranslationSeeder::class);
```

**Features:**
- Auto-imports translations from files when database is empty
- Configurable via `restore_on_seed` option
- Supports both JSON and PHP array formats

---

## 🧰 Helper `___()`

The `___()` helper uses a **file-first approach** with database fallback and automatic key creation.

```blade
{{ ___('Dashboard') }}               // default scope = app
{{ ___('Users', 'admin') }}          // custom scope
```

**How it works:**

1. **File Lookup**: Checks per-scope translation files first (fastest)
2. **Database Fallback**: If not found in files, checks database
3. **Auto-Creation**: If missing everywhere, creates DB key and stores key-as-value
4. **In-Memory Cache**: Caches results per request for optimal performance

---

## 🛠 Commands

### Scan

Extract translation keys from source files and persist them to database:

```bash
php artisan simple-translation:scan --paths=app,resources --scope=app
```

**Options:**
- `--paths` - CSV directories to scan (default: `app,resources`)
- `--ext` - CSV extensions to include (`php,blade.php,vue,js,ts`)
- `--scope` - Assign scope for found keys
- `--exclude` - CSV directories to skip (default: `vendor,node_modules,storage`)
- `--dry` - Dry-run mode (no database writes)
- `--no-progress` - Hide progress bar

### Export

Export database translations to per-scope files:

```bash
php artisan simple-translation:export --scope=app
php artisan simple-translation:export --all  # Export all configured scopes
```

**Options:**
- `--scope` - CSV scopes to export (e.g., `app,admin`)
- `--all` - Export all scopes from config
- `--locales` - CSV locales filter (e.g., `en,ru`)

### Import

Import per-scope files into database:

```bash
php artisan simple-translation:import --scope=app
php artisan simple-translation:import --all --truncate  # Truncate before import
```

**Options:**
- `--scope` - CSV scopes to import
- `--all` - Import all configured scopes
- `--locales` - CSV locales filter
- `--truncate` - Force truncate before import

### Sync

Run **scan + export** in one step:

```bash
php artisan simple-translation:sync --scope=app
```

**Options:**
- `--paths`, `--ext`, `--exclude` - Scan options
- `--scope`, `--all`, `--locales` - Export options
- `--dry` - Only scan (no database writes, no export)
- `--no-progress` - Hide progress bar

**When to use `sync`:**
- **Deploy pipelines**: Update database keys and regenerate files in one command
- **Initial setup**: Populate database from code and immediately export
- **Development workflow**: Keep files and database in sync

---

## 📤 File Storage & Formats

Translations are stored in per-scope files with configurable formats:

### JSON Format (Default)
```
lang/
├── en/
│   ├── app.json          # {"Welcome": "Welcome", "Dashboard": "Dashboard"}
│   └── admin.json        # {"Users": "Users", "Settings": "Settings"}
└── ru/
    ├── app.json          # {"Welcome": "Добро пожаловать", "Dashboard": "Панель"}
    └── admin.json
```

### PHP Array Format
```
lang/vendor/simple-translation/
├── en/
│   ├── app.php           # <?php return ['Welcome' => 'Welcome', ...];
│   └── admin.php
└── ru/
    ├── app.php
    └── admin.php
```

**Configuration:**
```php
'translations' => [
    'driver' => 'json-per-scope', // or 'php-array-per-scope'
    'drivers' => [
        'json-per-scope' => [
            'base_dir' => lang_path(),
            'pretty' => false,
            'lock' => true,
        ],
        'php-array-per-scope' => [
            'base_dir' => lang_path('vendor/simple-translation'),
            'lock' => true,
        ],
    ],
],
```

---

## ⚡ Performance & Caching

### In-Memory Caching
- **Per-request memoization**: Always enabled for optimal performance
- **File-first lookup**: Reads from files before checking database
- **Automatic cache refresh**: Updates when translations are modified

### Performance Tips
- **Use scopes**: Organize translations by scope to keep file sizes manageable
- **File-based storage**: Faster than database lookups for repeated access
- **First request**: Loads files into memory, subsequent calls are O(1)
- **Database fallback**: Only used when translations are missing from files

---

## 🧱 Database Schema

### Tables

**`app_languages`**
- `id` - Primary key
- `name` - Language name (e.g., "English")
- `code` - Language code (e.g., "en")
- `icon` - Optional icon/flag
- `is_active` - Active status

**`app_texts`**
- `id` - Primary key
- `scope` - Translation scope (e.g., "app", "admin")
- `text` - Original text/key

**`app_text_translations`**
- `id` - Primary key
- `app_text_id` - Foreign key to app_texts
- `lang_code` - Language code
- `text` - Translated text

### Relationships
- `AppText` has many `AppTextTranslation`
- `AppTextTranslation` belongs to `AppText`
- Cascade delete ensures translations are removed with their parent text

---

## 🛠 Troubleshooting

### Common Issues

**`no such table: app_languages`**
- Publish and run migrations: `php artisan vendor:publish --tag=simple-translation-migrations && php artisan migrate`

**Helper returns key instead of translation**
- Add translation for that locale and scope
- Check if files exist in the correct location
- Verify locale is active in database or config

**Files not being created**
- Check file permissions on `lang/` directory
- Verify store driver configuration
- Run export command manually: `php artisan simple-translation:export --scope=app`

**Import/Export not working**
- Verify database connection
- Check if tables exist and are accessible
- Ensure locales are configured correctly

---

## ❓ FAQ

**How is `___()` different from Laravel `__()`?**
- `___()` uses file-first approach with database fallback and auto-creates missing keys
- `__()` only reads from Laravel's translation files

**Can I use config locales instead of database?**
- Yes, set `use_locales_from=config` in configuration

**What file formats are supported?**
- JSON (default): `{locale}/{scope}.json`
- PHP Array: `{locale}/{scope}.php` in vendor directory

**How do scopes work?**
- Scopes organize translations into separate files (e.g., `app.json`, `admin.json`)
- Use `--scope` parameter in commands to target specific scopes
- Configure available scopes in `available_scopes` config

**Can I disable database usage?**
- No, database is required for the file-first approach to work properly
- Files are the primary source, database serves as backup and key management

---

## 🧭 Versioning & Compatibility

- **Semantic Versioning**: Follows SemVer specification
- **Laravel**: ^10.0|^11.0|^12.0
- **PHP**: ^8.1
- **Database**: SQLite/MySQL/PostgreSQL supported

---

## 🔐 Security

Report security issues privately to **bexruz.aslonov1@gmail.com**.

---

## 📄 License

MIT License.
