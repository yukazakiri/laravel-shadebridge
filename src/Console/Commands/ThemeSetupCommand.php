<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

final class ThemeSetupCommand extends Command
{
    protected $signature = 'theme:setup
                            {--mode= : Setup mode: starter or standalone}
                            {--stack= : Frontend stack: react or vue}
                            {--force : Overwrite existing files without prompting}
                            {--skip-existing : Keep existing files instead of overwriting them}
                            {--dry-run : Show files that would be written without changing them}';

    protected $description = 'Set up theme tooling and starter kits';

    protected $aliases = ['theme:install'];

    public function handle(): int
    {
        $setupChoice = $this->resolveChoice(
            option: 'mode',
            allowed: ['starter', 'standalone'],
            label: 'How would you like to set up themes?',
            options: [
                'starter' => 'Starter kit template (includes UI components)',
                'standalone' => 'Stand-alone configuration (hook/composable only)',
            ],
            default: 'starter',
            hint: 'Starter kit includes pre-built theme switcher components',
        );

        if ($setupChoice === null) {
            return self::FAILURE;
        }

        if ($setupChoice === 'standalone') {
            return $this->installStandalone();
        }

        $stack = $this->resolveStackChoice('Which starter kit would you like to install?');

        if ($stack === null) {
            return self::FAILURE;
        }

        info(sprintf('Installing the %s starter kit...', $stack === 'react' ? 'Inertia React' : 'Inertia Vue'));

        $result = $stack === 'react' ? $this->installReactStarterKit() : $this->installVueStarterKit();

        if ($result === self::SUCCESS) {
            note('');
            info('Starter kit installed successfully.');
            note('Run <comment>npm run dev</comment> or <comment>npm run build</comment> to apply the styles.');
        }

        return $result;
    }

    private function installStandalone(): int
    {
        $stack = $this->resolveStackChoice('Which stack are you using?');

        if ($stack === null) {
            return self::FAILURE;
        }

        info(sprintf('Setting up stand-alone theme configuration for %s...', $stack === 'react' ? 'Inertia React' : 'Inertia Vue'));

        $stubFiles = [
            'resources/js/conf/themes.ts' => resource_path('js/conf/themes.ts'),
            'resources/css/themes/rose.css' => resource_path('css/themes/rose.css'),
            'resources/css/themes/ocean.css' => resource_path('css/themes/ocean.css'),
        ];

        if ($stack === 'react') {
            $stubFiles['resources/js/hooks/use-color-theme.tsx'] = resource_path('js/hooks/use-color-theme.tsx');
        } else {
            $stubFiles['resources/js/composables/useColorTheme.ts'] = resource_path('js/composables/useColorTheme.ts');
        }

        $result = $this->processInstall($stack, $stubFiles, function () use ($stack): void {
            if ($stack === 'react') {
                $this->updateAppTsx();
            } else {
                $this->updateAppVue();
            }
        });

        if ($result === self::SUCCESS) {
            note('');
            info('Stand-alone configuration set up successfully.');
            info('You can now use the `useColorTheme` hook/composable to build your own UI.');
            note('Run <comment>npm run dev</comment> or <comment>npm run build</comment> to apply the styles.');
        }

        return $result;
    }

    private function installReactStarterKit(): int
    {
        $stubFiles = [
            'resources/js/conf/themes.ts' => resource_path('js/conf/themes.ts'),
            'resources/js/hooks/use-color-theme.tsx' => resource_path('js/hooks/use-color-theme.tsx'),
            'resources/js/components/theme-switcher.tsx' => resource_path('js/components/theme-switcher.tsx'),
            'resources/css/themes/rose.css' => resource_path('css/themes/rose.css'),
            'resources/css/themes/ocean.css' => resource_path('css/themes/ocean.css'),
            'resources/js/pages/settings/profile.tsx' => resource_path('js/pages/settings/profile.tsx'),
        ];

        return $this->processInstall('react', $stubFiles, function (): void {
            $this->updateAppTsx();
        });
    }

    private function resolveStackChoice(string $label): ?string
    {
        return $this->resolveChoice(
            option: 'stack',
            allowed: ['react', 'vue'],
            label: $label,
            options: [
                'react' => 'Inertia React',
                'vue' => 'Inertia Vue',
            ],
            default: 'react',
            hint: 'Choose the stack matching your application',
        );
    }

    /**
     * @param  array<int, string>  $allowed
     * @param  array<string, string>  $options
     */
    private function resolveChoice(string $option, array $allowed, string $label, array $options, string $default, string $hint): ?string
    {
        $value = $this->option($option);

        if ($value !== null) {
            $value = (string) $value;

            if (! in_array($value, $allowed, true)) {
                error(sprintf('Invalid --%s value "%s". Expected one of: %s.', $option, $value, implode(', ', $allowed)));

                return null;
            }

            return $value;
        }

        return (string) select(
            label: $label,
            options: $options,
            default: $default,
            hint: $hint,
        );
    }

    private function installVueStarterKit(): int
    {
        $stubFiles = [
            'resources/js/conf/themes.ts' => resource_path('js/conf/themes.ts'),
            'resources/js/composables/useColorTheme.ts' => resource_path('js/composables/useColorTheme.ts'),
            'resources/js/components/ThemeSwitcher.vue' => resource_path('js/components/ThemeSwitcher.vue'),
            'resources/css/themes/rose.css' => resource_path('css/themes/rose.css'),
            'resources/css/themes/ocean.css' => resource_path('css/themes/ocean.css'),
            'resources/js/pages/settings/Profile.vue' => resource_path('js/pages/settings/Profile.vue'),
        ];

        return $this->processInstall('vue', $stubFiles, function (): void {
            $this->updateAppVue();
        });
    }

    private function processInstall(string $stack, array $stubFiles, callable $updateAppCallback): int
    {
        foreach ($stubFiles as $stub => $destination) {
            if (! $this->publishStub($stack, $stub, $destination)) {
                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            info('Dry run complete. No files were changed.');

            return self::SUCCESS;
        }

        $this->updateAppCssImports();
        $updateAppCallback();

        return self::SUCCESS;
    }

    private function publishStub(string $stack, string $stub, string $destination): bool
    {
        $stubPath = $this->stubPath($stack, $stub);

        if (! File::exists($stubPath)) {
            $this->error('Missing stub file: '.$stubPath);

            return false;
        }

        if (File::exists($destination)) {
            if ($this->option('skip-existing')) {
                warning('  - Skipped existing file: '.$destination);

                return true;
            }

            if (! $this->option('force') && ! $this->option('dry-run') && ! confirm('Overwrite existing file '.$destination.'?', false)) {
                warning('  - Skipped existing file: '.$destination);

                return true;
            }
        }

        if ($this->option('dry-run')) {
            info((File::exists($destination) ? '  - Would overwrite: ' : '  - Would write: ').$destination);

            return true;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, File::get($stubPath));

        info('  ✓ Wrote: '.$destination);

        return true;
    }

    private function updateAppCssImports(): void
    {
        $appCssPath = resource_path('css/app.css');
        if (! File::exists($appCssPath)) {
            warning('app.css not found, skipping CSS imports update.');

            return;
        }

        $content = File::get($appCssPath);

        $imports = [
            "@import './themes/rose.css';",
            "@import './themes/ocean.css';",
        ];

        $updated = $content;

        foreach ($imports as $import) {
            if (Str::contains($updated, $import)) {
                continue;
            }

            $updated = $this->insertImport($updated, $import);
        }

        if ($updated !== $content) {
            File::put($appCssPath, $updated);
            info('  ✓ Updated app.css with theme imports.');
        }

        $this->ensureAppCssMappings($appCssPath);
    }

    private function ensureAppCssMappings(string $appCssPath): void
    {
        $content = File::get($appCssPath);
        $originalContent = $content;

        // Fix sidebar color mapping if it points to sidebar-background
        if (Str::contains($content, '--color-sidebar: var(--sidebar-background);')) {
            $content = str_replace(
                '--color-sidebar: var(--sidebar-background);',
                '--color-sidebar: var(--sidebar);',
                $content
            );
        }

        // Remove the problematic @layer utilities block that overrides fonts
        $content = preg_replace(
            '/@layer\s+utilities\s*\{\s*body,\s*html\s*\{[^}]*--font-sans:[^}]*\}\s*\}/s',
            '',
            $content
        );

        // Ensure body applies font-sans class
        if (Str::contains($content, '@apply bg-background text-foreground;') && ! Str::contains($content, 'font-sans')) {
            $content = str_replace(
                '@apply bg-background text-foreground;',
                '@apply bg-background text-foreground font-sans;',
                $content
            );
        }

        // Ensure @theme mappings
        if (! Str::contains($content, '--shadow-2xs: var(--shadow-2xs);')) {
            $mappings = <<<'CSS'
    /* Shadow mappings for theme support */
    --shadow-2xs: var(--shadow-2xs);
    --shadow-xs: var(--shadow-xs);
    --shadow-sm: var(--shadow-sm);
    --shadow: var(--shadow);
    --shadow-md: var(--shadow-md);
    --shadow-lg: var(--shadow-lg);
    --shadow-xl: var(--shadow-xl);
    --shadow-2xl: var(--shadow-2xl);

    /* Font mappings for theme support */
    --font-sans: var(--font-sans);
    --font-mono: var(--font-mono);
    --font-serif: var(--font-serif);
CSS;
            if (preg_match('/@theme(\s+inline)?\s*\{.*?\}/s', $content, $matches)) {
                $themeBlock = $matches[0];
                $lastBracePos = strrpos($themeBlock, '}');
                if ($lastBracePos !== false) {
                    $newThemeBlock = substr($themeBlock, 0, $lastBracePos)."\n".$mappings."\n}";
                    $content = str_replace($themeBlock, $newThemeBlock, $content);
                }
            }
        }

        // Ensure :root defaults
        if (! Str::contains($content, '--shadow-2xs: 0 1px rgb(0 0 0 / 0.05);')) {
            $defaults = <<<'CSS'
    /* Default fonts */
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
    --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    --font-serif: ui-serif, Georgia, Cambria, 'Times New Roman', Times, serif;

    /* Default shadows (Tailwind defaults) */
    --shadow-2xs: 0 1px rgb(0 0 0 / 0.05);
    --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
CSS;
            if (preg_match('/:root\s*\{.*?\}/s', $content, $matches)) {
                $rootBlock = $matches[0];
                $lastBracePos = strrpos($rootBlock, '}');
                if ($lastBracePos !== false) {
                    $newRootBlock = substr($rootBlock, 0, $lastBracePos)."\n".$defaults."\n}";
                    $content = str_replace($rootBlock, $newRootBlock, $content);
                }
            }
        }

        if ($content !== $originalContent) {
            File::put($appCssPath, $content);
            info('  ✓ Updated app.css with shadow and font mappings.');
        }
    }

    private function insertImport(string $content, string $import): string
    {
        $pattern = '/@import\s+["\']tw-animate-css["\'];/';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0];
            $insertPosition = $match[1] + mb_strlen($match[0]);

            return mb_substr($content, 0, $insertPosition).('

'.$import).mb_substr($content, $insertPosition);
        }

        if (Str::contains($content, '@source')) {
            return str_replace('@source', $import.'

@source', $content);
        }

        return $import.PHP_EOL.$content;
    }

    private function updateAppTsx(): void
    {
        $appPath = resource_path('js/app.tsx');
        if (! File::exists($appPath)) {
            return;
        }

        $content = File::get($appPath);
        $updated = $content;

        if (! Str::contains($updated, 'initializeColorTheme')) {
            $updated = str_replace(
                "import { initializeTheme } from './hooks/use-appearance';",
                "import { initializeTheme } from './hooks/use-appearance';\nimport { initializeColorTheme } from './hooks/use-color-theme';",
                $updated,
            );
        }

        if (! Str::contains($updated, 'initializeColorTheme();')) {
            $updated = str_replace(
                'initializeTheme();',
                "initializeTheme();\ninitializeColorTheme();",
                $updated,
            );
        }

        if ($updated !== $content) {
            File::put($appPath, $updated);
            info('  ✓ Updated app.tsx to initialize theme colors.');
        }
    }

    private function updateAppVue(): void
    {
        $appPath = resource_path('js/app.ts');
        if (! File::exists($appPath)) {
            $appPath = resource_path('js/app.js');
        }

        if (! File::exists($appPath)) {
            return;
        }

        $content = File::get($appPath);
        $updated = $content;

        if (! Str::contains($updated, 'initializeColorTheme')) {
            $import = "import { initializeColorTheme } from './composables/useColorTheme';";

            if (Str::contains($updated, 'import { initializeTheme }')) {
                $updated = str_replace(
                    'import { initializeTheme }',
                    "import { initializeColorTheme } from './composables/useColorTheme';\nimport { initializeTheme }",
                    $updated
                );
            } else {
                $updated = $import."\n".$updated;
            }
        }

        if (! Str::contains($updated, 'initializeColorTheme();')) {
            if (Str::contains($updated, 'initializeTheme();')) {
                $updated = str_replace(
                    'initializeTheme();',
                    "initializeTheme();\ninitializeColorTheme();",
                    $updated,
                );
            } elseif (Str::contains($updated, 'createInertiaApp')) {
                $updated = str_replace(
                    'createInertiaApp({',
                    "initializeColorTheme();\n\ncreateInertiaApp({",
                    $updated
                );
            }
        }

        if ($updated !== $content) {
            File::put($appPath, $updated);
            info('  ✓ Updated app.ts/js to initialize theme colors.');
        }
    }

    private function stubPath(string $stack, string $path): string
    {
        return __DIR__.'/../../../stubs/'.$stack.'/'.$path;
    }
}
