<?php

declare(strict_types=1);

namespace Yukzakiri\ThemeTools\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Yukzakiri\ThemeTools\Support\ThemeConfigFile;

final class DefaultThemeCommand extends Command
{
    protected $signature = 'theme:default {theme? : Installed theme ID} {--reset : Use the original default palette}';

    protected $description = 'Show or set the system color theme default (rebuild assets afterward)';

    public function handle(): int
    {
        $path = resource_path('js/conf/themes.ts');
        if (! File::exists($path)) {
            $this->error('Missing theme catalog. Run theme:setup first.');

            return self::FAILURE;
        }
        $catalog = new ThemeConfigFile($path);
        $theme = $this->option('reset') ? 'default' : $this->argument('theme');
        if ($theme === null) {
            $this->info('System default: '.$catalog->defaultTheme());

            return self::SUCCESS;
        }
        try {
            $catalog->setDefaultTheme($theme);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('System default: '.$theme.'. Rebuild frontend assets to apply. Existing browser choices are preserved.');

        return self::SUCCESS;
    }
}
