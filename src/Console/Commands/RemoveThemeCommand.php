<?php

declare(strict_types=1);

namespace Yukzakiri\ThemeTools\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Yukzakiri\ThemeTools\Support\ThemeConfigFile;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

final class RemoveThemeCommand extends Command
{
    protected $signature = 'theme:remove
                            {theme? : The theme ID to remove (e.g. catppuccin)}
                            {--list : List all available themes}
                            {--force : Remove without confirmation}
                            {--all : Remove all non-protected themes}';

    protected $description = 'Remove one or more themes from the application';

    private string $themesDir;

    private string $themesConfigPath;

    private string $appCssPath;

    /** @var array<string> */
    private array $protectedThemes = ['default'];

    public function handle(): int
    {
        $this->themesDir = resource_path('css/themes');
        $this->themesConfigPath = resource_path('js/conf/themes.ts');
        $this->appCssPath = resource_path('css/app.css');

        if ($this->option('list')) {
            return $this->listThemes();
        }

        // Handle --all flag to remove all non-protected themes
        if ($this->option('all')) {
            return $this->removeAllThemes();
        }

        $themeId = $this->argument('theme');

        if ($themeId) {
            return $this->removeSingleTheme($themeId);
        }

        // No theme specified, use interactive selection
        return $this->interactiveRemoval();
    }

    private function removeSingleTheme(string $themeId): int
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $themeId)) {
            error('Invalid theme ID.');

            return self::FAILURE;
        }

        if (in_array($themeId, $this->protectedThemes)) {
            error(sprintf("The '%s' theme is protected and cannot be removed.", $themeId));

            return self::FAILURE;
        }

        if (! $this->themeExists($themeId)) {
            error(sprintf("Theme '%s' does not exist.", $themeId));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(sprintf("Are you sure you want to remove the '%s' theme?", $themeId), false)) {
            info('Removal cancelled.');

            return self::SUCCESS;
        }

        if (! $this->hasRequiredThemeFiles()) {
            return self::FAILURE;
        }

        $this->removeTheme($themeId);

        return self::SUCCESS;
    }

    private function interactiveRemoval(): int
    {
        $themes = $this->getAvailableThemes();

        if ($themes === []) {
            error('No themes available to remove.');

            return self::FAILURE;
        }

        // Filter out protected themes for removal options
        $removableThemes = array_filter($themes, fn ($theme): bool => ! in_array($theme, $this->protectedThemes));

        if ($removableThemes === []) {
            warning('All installed themes are protected and cannot be removed.');

            return self::SUCCESS;
        }

        // Build options with protection status
        $themeOptions = [];
        foreach ($removableThemes as $theme) {
            $themeOptions[$theme] = $theme;
        }

        $selectedThemes = multiselect(
            label: 'Which themes would you like to remove?',
            options: $themeOptions,
            hint: 'Use space to select multiple themes, enter to confirm'
        );

        if ($selectedThemes === []) {
            info('No themes selected for removal.');

            return self::SUCCESS;
        }

        // Verify all selected themes exist and are not protected
        foreach ($selectedThemes as $themeId) {
            if (in_array($themeId, $this->protectedThemes)) {
                error(sprintf("The '%s' theme is protected and cannot be removed.", $themeId));

                return self::FAILURE;
            }

            if (! $this->themeExists($themeId)) {
                error(sprintf("Theme '%s' does not exist.", $themeId));

                return self::FAILURE;
            }
        }

        // Confirm removal
        $themeList = implode(', ', $selectedThemes);
        if (! $this->option('force') && ! confirm(sprintf('Are you sure you want to remove the following themes: %s?', $themeList), false)) {
            info('Removal cancelled.');

            return self::SUCCESS;
        }

        if (! $this->hasRequiredThemeFiles()) {
            return self::FAILURE;
        }

        // Remove each selected theme
        foreach ($selectedThemes as $themeId) {
            $this->removeTheme($themeId);
        }

        note('');
        info(count($selectedThemes) > 1 ? 'All selected themes removed successfully!' : 'Theme removed successfully!');
        note('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the changes.');

        return self::SUCCESS;
    }

    private function removeAllThemes(): int
    {
        $themes = $this->getAvailableThemes();
        $removableThemes = array_filter($themes, fn (string $theme): bool => ! in_array($theme, $this->protectedThemes));

        if ($removableThemes === []) {
            warning('No removable themes found.');

            return self::SUCCESS;
        }

        $themeList = implode(', ', $removableThemes);
        note(sprintf('The following themes will be removed: %s', $themeList));

        if (! $this->option('force') && ! confirm('Are you sure you want to remove all these themes?', false)) {
            info('Removal cancelled.');

            return self::SUCCESS;
        }

        if (! $this->hasRequiredThemeFiles()) {
            return self::FAILURE;
        }

        foreach ($removableThemes as $themeId) {
            $this->removeTheme($themeId);
        }

        note('');
        info('All themes removed successfully!');
        note('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the changes.');

        return self::SUCCESS;
    }

    private function removeTheme(string $themeId): void
    {
        info(sprintf('Removing theme: %s', $themeId));

        $this->removeCssFile($themeId);
        $this->removeAppCssImport($themeId);
        $this->removeThemesConfig($themeId);
    }

    private function listThemes(): int
    {
        $themes = $this->getAvailableThemes();

        if ($themes === []) {
            info('No custom themes installed.');

            return self::SUCCESS;
        }

        note('Available themes:');
        note('');

        foreach ($themes as $theme) {
            $protected = in_array($theme, $this->protectedThemes) ? ' (protected)' : '';
            note(sprintf('  - %s%s', $theme, $protected));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function getAvailableThemes(): array
    {
        $files = File::isDirectory($this->themesDir) ? File::files($this->themesDir) : [];
        $themes = File::exists($this->themesConfigPath)
            ? (new ThemeConfigFile($this->themesConfigPath))->ids()
            : [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'css') {
                $themes[] = $file->getFilenameWithoutExtension();
            }
        }

        if (! in_array('default', $themes)) {
            array_unshift($themes, 'default');
        }

        $themes = array_values(array_unique($themes));
        sort($themes);

        return $themes;
    }

    private function themeExists(string $themeId): bool
    {
        if ($themeId === 'default') {
            return true;
        }

        $cssFile = sprintf('%s/%s.css', $this->themesDir, $themeId);

        return File::exists($cssFile) || (File::exists($this->themesConfigPath)
            && in_array($themeId, (new ThemeConfigFile($this->themesConfigPath))->ids(), true));
    }

    private function hasRequiredThemeFiles(): bool
    {
        foreach ([$this->appCssPath, $this->themesConfigPath] as $path) {
            if (File::exists($path)) {
                continue;
            }

            error(sprintf('Missing %s. Run <comment>php artisan theme:setup</comment> before removing themes.', $path));

            return false;
        }

        return true;
    }

    private function removeCssFile(string $themeId): void
    {
        $cssFile = sprintf('%s/%s.css', $this->themesDir, $themeId);

        if (File::exists($cssFile)) {
            File::delete($cssFile);
            info('  ✓ Removed CSS file: '.$cssFile);
        } else {
            note('  - CSS file not found: '.$cssFile);
        }
    }

    private function removeAppCssImport(string $themeId): void
    {
        $appCss = File::get($this->appCssPath);

        $themeId = preg_quote($themeId, '/');
        $patterns = [
            '/@import\s+["\']\.\/themes\/'.$themeId.'\.css["\'];\n?/',
            '/@import\s+["\']\.\.\/css\/themes\/'.$themeId.'\.css["\'];\n?/',
        ];

        $modified = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, (string) $appCss)) {
                $appCss = preg_replace($pattern, '', (string) $appCss);
                $modified = true;
            }
        }

        if ($modified) {
            $appCss = preg_replace('/\n{3,}/', "\n\n", (string) $appCss);
            File::put($this->appCssPath, $appCss);
            info('  ✓ Removed import from app.css');
        } else {
            note('  - Import not found in app.css');
        }
    }

    private function removeThemesConfig(string $themeId): void
    {
        if ((new ThemeConfigFile($this->themesConfigPath))->removeTheme($themeId)) {
            info('  ✓ Removed theme from themes.ts configuration');
        } else {
            note('  - Theme not found in themes.ts configuration');
        }
    }
}
