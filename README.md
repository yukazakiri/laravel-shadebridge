# ShadcThemes Tools

Powerful Artisan commands to seamlessly integrate and manage [tweakcn](https://tweakcn.com) / shadcn themes in your Laravel Inertia applications.

Support for **Inertia React** and **Inertia Vue**.

## Features

- 🎨 **Instant Theme Setup**: Scaffolds theme configuration, hooks, and UI components in seconds.
- 📥 **One-Command Import**: Import any theme from `tweakcn.com` directly via URL.
- 🌗 **Dark Mode Ready**: Automatically handles CSS variables for light and dark modes.
- 💅 **Tailwind CSS Integration**: Injects necessary shadow and font mappings into your `app.css`.
- ⚛️ **Multi-Stack Support**: First-class support for both React and Vue (Inertia).

## Requirements

- PHP 8.2+ for Laravel 12 projects, PHP 8.4+ for Laravel 13 projects
- Laravel 12 or 13
- Inertia.js (React or Vue)
- Tailwind CSS

## Installation

Install the package via Composer:

```bash
composer require yukzakiri/shadcthemes-tools
```

## Quick Start

### 1. Setup the Environment

Run the setup command to initialize the theming system in your application.

```bash
php artisan theme:setup
```

For non-interactive installs, pass the setup choices directly:

```bash
php artisan theme:setup --mode=starter --stack=react --force
php artisan theme:setup --mode=standalone --stack=vue --skip-existing
```

`theme:install` is also available as an alias for first-time setup.

Useful setup options:

```bash
php artisan theme:setup --dry-run
php artisan theme:setup --skip-existing
php artisan theme:setup --force
```

The setup command will now warn before overwriting existing files unless `--force` is used. Use `--skip-existing` to preserve customized files.

You will be presented with two options:

- **Starter Kit Template**: Installs a full suite of components (`ThemeSwitcher`, `useColorTheme`), configures `app.css`, and even sets up a "Personalization" section in your Profile page settings. Ideal for fresh projects or those following standard starter kit structures.
- **Stand-alone Configuration**: Installs only the core plumbing (CSS files, config, and state management hooks). Perfect if you want to build your own UI or integrate into an existing custom layout without overwriting files.

### 2. Build Assets

After setup, rebuild your frontend assets to compile the new CSS variables:

```bash
npm run dev
# or
npm run build
```

## Usage

### Importing Themes

Find a theme you like on [shadcn themes](https://shadcnthemer.com) or [tweakcn](https://tweakcn.com), copy its JSON URL, and run:

```bash
php artisan theme:add https://shadcnthemer.com/r/themes/your-theme-id.json
```

If the package has not been set up yet, the command will stop with a setup hint instead of failing with a filesystem error.

This command will:

1. Download the theme definition.
2. Generate a CSS file in `resources/css/themes/`.
3. Register the theme in `resources/js/conf/themes.ts`.
4. Add the necessary imports to `app.css`.

Both `theme:add` and `theme:import` use the same importer. Supported JSON has a non-empty `cssVars.light` object, optional `cssVars.theme` and `cssVars.dark` objects, and optional simple `css["@layer base"]` rules. Registry files, scripts, arbitrary CSS layers, URL-valued declarations, and nested selectors are not installed. Import only registries you trust. HTTP errors, invalid JSON, and unsupported declarations fail before source changes; HTTPS certificate verification remains enabled.

```bash
php artisan theme:import https://tweakcn.com/r/themes/kodama-grove.json
php artisan theme:import https://tweakcn.com/r/themes/kodama-grove.json --force --description="Updated palette"
```

`--force` replaces CSS and catalog metadata. `--name` overrides the ID/display name. The `default` ID is reserved for the application's original palette.

### System default

```bash
php artisan theme:default kodama-grove
php artisan theme:default
php artisan theme:default --reset
npm run build
```

The command writes `defaultColorTheme` to `resources/js/conf/themes.ts`. React and Vue hooks use it when no valid browser choice exists. Existing valid choices remain unchanged. Removing the selected system default resets it to `default`. Missing/removed browser choices fall back without persisting the fallback, allowing future system-default changes to apply.

For customized hooks, import `defaultColorTheme`, validate stored IDs against `themes`, and remove all existing `theme-*` classes before applying the selected ID. Preserve your app's existing storage key.

To apply the same choice before hydration, include this in the document head before frontend scripts (after your light/dark initializer):

```blade
@include('theme-tools::initialize')
{{-- Custom storage key: --}}
@include('theme-tools::initialize', ['storageKey' => 'ui-color-theme'])
```

New scaffolds export `defaultColorTheme`. Existing installations can add `export const defaultColorTheme: ColorTheme = 'default';` to their catalog before adopting the updated hooks. Do not run `theme:setup --force` over customized profile pages.

### Removing Themes

To remove a theme and clean up its files:

```bash
php artisan theme:remove theme-name
php artisan theme:remove --list
php artisan theme:remove theme-name --force
# Interactive multi-select:
php artisan theme:remove
```

Removal cleans CSS imports, files, catalog entries, and type IDs, including catalog-only stale themes. `default` is protected. Rebuild assets after every import, removal, or default change.

### Updating Components

If the package updates its UI stubs (like the Theme Switcher component), you can pull the latest versions into your app:

```bash
php artisan theme:update
```

For non-interactive updates:

```bash
php artisan theme:update --stack=react --force
php artisan theme:update --stack=vue --force
```

### Diagnosing Setup

Check whether the package has the files and frontend dependencies it expects:

```bash
php artisan theme:doctor
```

## Verification

```bash
composer test
composer analyse
vendor/bin/pint --test
# Node 22.13+ (TypeScript stripping; no npm dependencies required):
node --test tests/frontend/color-theme.test.mjs
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to set up a local development environment and contribute to this project.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
