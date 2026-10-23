# Contributing to Shadebridge for Laravel

Thank you for considering contributing to Shadebridge for Laravel! This guide helps you set up a local development environment to work on the package.

## Local Development Setup

To develop this package locally, you typically link it to a Laravel application (like `theme-sample` or a fresh installation) using a "path repository".

### Prerequisites

- PHP 8.2+ for Laravel 12 testing, PHP 8.4+ for Laravel 13 testing
- Composer
- Laravel 12+ (for testing the integration)

### Linking to a Local Project

1.  **Configure `composer.json` in your test app:**

    Add a path repository pointing to your local clone of this package:

    ```json
    "repositories": [
        {
            "type": "path",
            "url": "../path/to/theme-tools",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "yukazakiri/laravel-shadebridge": "@dev"
    }
    ```

    _Replace `../path/to/theme-tools` with the actual absolute or relative path._

2.  **Install the package:**

    ```bash
    composer update yukazakiri/laravel-shadebridge
    ```

### Developing with Laravel Sail

If you are using Laravel Sail, you must mount the package volume into the container so changes are reflected immediately.

**`docker-compose.yml` configuration:**

```yaml
services:
  laravel.test:
    volumes:
      - ".:/var/www/html"
      - "/absolute/path/to/theme-tools:/var/www/theme-tools"
```

Then update your `composer.json` to point to `/var/www/theme-tools` inside the container.

### Running Tests and Analysis

This package includes a suite of tools to ensure code quality.

**Run Unit & Feature Tests:**

```bash
composer test
# or
vendor/bin/pest
```

**Run Static Analysis (PHPStan):**

```bash
composer analyse
# or
php -d memory_limit=512M vendor/bin/phpstan analyse
```

**Run Code Linting (Pint):**

```bash
composer lint
# or
vendor/bin/pint
```

**Run Automated Refactoring (Rector):**

```bash
composer refactor
# or
vendor/bin/rector process
```

Please ensure all checks pass before submitting a Pull Request.
