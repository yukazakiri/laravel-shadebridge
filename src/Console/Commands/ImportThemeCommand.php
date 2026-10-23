<?php

declare(strict_types=1);

namespace Yukzakiri\ThemeTools\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Yukzakiri\ThemeTools\Support\ThemeConfigFile;
use Yukzakiri\ThemeTools\Support\ThemeDefinition;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class ImportThemeCommand extends Command
{
    protected $signature = 'theme:import
                            {url? : The URL to the theme JSON file (e.g. https://tweakcn.com/r/themes/vintage-paper.json)}
                            {--name= : Override the theme name}
                            {--description= : Custom description for the theme}
                            {--force : Overwrite an existing theme without confirmation}';

    protected $description = 'Import a theme from a tweakcn or shadcn registry JSON URL';

    private string $themesDir;

    private string $themesConfigPath;

    private string $appCssPath;

    public function handle(): int
    {
        $this->themesDir = resource_path('css/themes');
        $this->themesConfigPath = resource_path('js/conf/themes.ts');
        $this->appCssPath = resource_path('css/app.css');

        $url = $this->argument('url');

        // If no URL provided, ask for it interactively
        if (! $url) {
            $url = text(
                label: 'Enter the theme JSON URL',
                placeholder: 'https://tweakcn.com/r/themes/vintage-paper.json',
                hint: 'You can find themes at tweakcn.com or use any shadcn-compatible JSON'
            );

            if (blank($url)) {
                error('A URL is required to import a theme.');

                return self::FAILURE;
            }
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
            error('Invalid URL provided.');

            return self::FAILURE;
        }

        if (! $this->hasRequiredThemeFiles()) {
            return self::FAILURE;
        }

        /** @var array<string, mixed>|null $themeData */
        $themeData = spin(
            callback: function () use ($url) {
                try {
                    /** @var Response $response */
                    $response = Http::connectTimeout(10)->timeout(30)
                        ->acceptJson()
                        ->get($url);

                    if (! $response->successful()) {
                        return null;
                    }

                    return $response->json();
                } catch (Exception $e) {
                    error('Failed to fetch theme: '.$e->getMessage());

                    return null;
                }
            },
            message: 'Fetching theme from: '.$url
        );

        if (! is_array($themeData)) {
            error('Failed to fetch or parse theme from the provided URL.');

            return self::FAILURE;
        }

        try {
            (new ThemeDefinition)->validate($themeData);
        } catch (\InvalidArgumentException $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        $rawThemeName = $this->option('name') ?? ($themeData['name'] ?? $this->extractThemeNameFromUrl($url));
        if (! is_string($rawThemeName) || Str::slug($rawThemeName) === '' || Str::slug($rawThemeName) === 'default') {
            error('Theme name must produce a non-empty ID other than the protected default.');

            return self::FAILURE;
        }

        $themeId = Str::slug($rawThemeName);
        $themeName = Str::title(str_replace(['-', '_'], ' ', $rawThemeName));

        if ($this->themeExists($themeId) && ! $this->option('force') && ! confirm(sprintf("Theme '%s' already exists. Overwrite?", $themeId), false)) {
            info('Import cancelled.');

            return self::SUCCESS;
        }

        info(sprintf('Importing theme: %s (id: %s)', $themeName, $themeId));

        // Preflight the catalog before writing CSS or imports.
        (new ThemeConfigFile($this->themesConfigPath))->ids();
        $cssContent = $this->generateCssFromThemeData($themeData, $themeId);
        $cssFilePath = sprintf('%s/%s.css', $this->themesDir, $themeId);

        File::ensureDirectoryExists($this->themesDir);
        File::put($cssFilePath, $cssContent);
        info('  ✓ Created CSS file: '.$cssFilePath);

        $this->updateAppCssImport($themeId);

        $description = $this->option('description') ?? 'Imported from tweakcn.';
        $this->updateThemesConfig($themeData, $themeId, $themeName, $description);

        note('');
        info('Theme imported successfully!');
        note('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the theme.');

        return self::SUCCESS;
    }

    private function extractThemeNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename((string) $path, '.json');

        return Str::title(str_replace(['-', '_'], ' ', $filename));
    }

    private function themeExists(string $themeId): bool
    {
        $cssFile = sprintf('%s/%s.css', $this->themesDir, $themeId);

        return File::exists($cssFile);
    }

    private function hasRequiredThemeFiles(): bool
    {
        foreach ([$this->appCssPath, $this->themesConfigPath] as $path) {
            if (File::exists($path)) {
                continue;
            }

            error(sprintf('Missing %s. Run <comment>php artisan theme:setup</comment> before importing themes.', $path));

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $themeData
     */
    private function generateCssFromThemeData(array $themeData, string $themeId): string
    {
        $cssVars = $themeData['cssVars'] ?? [];
        $css = $themeData['css'] ?? [];

        $fontImports = $this->extractFontImports($cssVars);
        $lightVars = $this->buildCssVariables($cssVars['light'] ?? [], $cssVars['theme'] ?? []);
        $darkVars = $this->buildCssVariables($cssVars['dark'] ?? [], $cssVars['theme'] ?? []);
        $additionalCss = $this->buildAdditionalCss($css, $themeId);

        $output = '';

        if ($fontImports !== '' && $fontImports !== '0') {
            $output .= $fontImports."\n";
        }

        $output .= ":root.theme-{$themeId},\n.theme-{$themeId} {\n";
        $output .= $lightVars;
        $output .= "}\n\n";

        $output .= ":root.dark.theme-{$themeId},\n.dark.theme-{$themeId} {\n";
        $output .= $darkVars;
        $output .= "}\n";

        if ($additionalCss !== '' && $additionalCss !== '0') {
            $output .= "\n".$additionalCss;
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $cssVars
     */
    private function extractFontImports(array $cssVars): string
    {
        $fonts = [];

        foreach (['theme', 'light', 'dark'] as $section) {
            if (! isset($cssVars[$section])) {
                continue;
            }

            foreach (['font-sans', 'font-mono', 'font-serif'] as $fontVar) {
                if (isset($cssVars[$section][$fontVar])) {
                    $fontValue = $cssVars[$section][$fontVar];
                    $primaryFont = trim(explode(',', $fontValue)[0]);
                    $primaryFont = trim($primaryFont, "\"'");

                    if (str_starts_with($primaryFont, 'var(')) {
                        continue;
                    }

                    if (in_array(mb_strtolower($primaryFont), ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace'], true)) {
                        continue;
                    }

                    $fonts[$primaryFont] = true;
                }
            }
        }

        $imports = [];
        foreach (array_keys($fonts) as $font) {
            $encodedFont = urlencode($font);
            $imports[] = sprintf("@import url('https://fonts.googleapis.com/css2?family=%s&display=swap');", $encodedFont);
        }

        return implode("\n", $imports);
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<string, string>  $themeVars
     */
    private function buildCssVariables(array $vars, array $themeVars = []): string
    {
        $allVars = array_merge($themeVars, $vars);

        $output = '';
        foreach ($allVars as $key => $value) {
            $output .= "  --{$key}: {$value};\n";
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $css
     */
    private function buildAdditionalCss(array $css, string $themeId): string
    {
        $output = '';

        foreach ($css as $layer => $rules) {
            if ($layer === '@layer base') {
                $output .= "@layer base {\n";
                foreach ($rules as $selector => $properties) {
                    $scopedSelector = implode(', ', array_map(
                        fn (string $part): string => sprintf('.theme-%s %s', $themeId, trim($part)),
                        explode(',', $selector),
                    ));
                    $output .= "  {$scopedSelector} {\n";
                    foreach ($properties as $prop => $value) {
                        $output .= "    {$prop}: {$value};\n";
                    }

                    $output .= "  }\n";
                }

                $output .= "}\n";
            }
        }

        return $output;
    }

    private function updateAppCssImport(string $themeId): void
    {
        $appCss = File::get($this->appCssPath);
        $importStatement = sprintf('@import "./themes/%s.css";', $themeId);

        if (preg_match('/@import\s+["\']\.\/themes\/'.preg_quote($themeId, '/').'\.css["\'];/', $appCss)) {
            note('  - Import already exists in app.css');

            return;
        }

        $pattern = '/@import\s+["\']\.\/themes\/[^"\']+["\'];/';
        if (preg_match_all($pattern, $appCss, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPosition = $lastMatch[1] + strlen($lastMatch[0]);
            $appCss = substr($appCss, 0, $insertPosition)."\n".$importStatement.substr($appCss, $insertPosition);
        } else {
            // A leading import stays valid even when the app has no animation library.
            $appCss = $importStatement."\n".$appCss;
        }

        File::put($this->appCssPath, $appCss);
        info('  ✓ Updated app.css with theme import');
    }

    /**
     * @param  array<string, mixed>  $themeData
     */
    private function updateThemesConfig(array $themeData, string $themeId, string $themeName, string $description): void
    {
        $cssVars = $themeData['cssVars'] ?? [];

        $font = 'System Sans';
        foreach (['theme', 'light'] as $section) {
            if (isset($cssVars[$section]['font-sans'])) {
                $fontValue = $cssVars[$section]['font-sans'];
                $font = trim(trim(explode(',', $fontValue)[0]), "\"'");
                break;
            }
        }

        $lightVars = $cssVars['light'] ?? [];
        $primary = $lightVars['primary'] ?? 'oklch(0.5 0.1 200)';
        $secondary = $lightVars['secondary'] ?? 'oklch(0.8 0.05 200)';
        $accent = $lightVars['accent'] ?? 'oklch(0.7 0.1 200)';

        $updated = (new ThemeConfigFile($this->themesConfigPath))->addTheme([
            'id' => $themeId,
            'name' => $themeName,
            'description' => $description,
            'font' => $font,
            'primary' => (string) $primary,
            'secondary' => (string) $secondary,
            'accent' => (string) $accent,
        ]);

        info($updated ? '  ✓ Updated themes.ts configuration' : '  - Theme config already exists.');
    }
}
