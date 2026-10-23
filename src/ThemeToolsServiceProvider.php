<?php

declare(strict_types=1);

namespace Yukzakiri\ThemeTools;

use Illuminate\Support\ServiceProvider;
use Yukzakiri\ThemeTools\Console\Commands\AddThemeCommand;
use Yukzakiri\ThemeTools\Console\Commands\DefaultThemeCommand;
use Yukzakiri\ThemeTools\Console\Commands\ImportThemeCommand;
use Yukzakiri\ThemeTools\Console\Commands\RemoveThemeCommand;
use Yukzakiri\ThemeTools\Console\Commands\ThemeDoctorCommand;
use Yukzakiri\ThemeTools\Console\Commands\ThemeSetupCommand;
use Yukzakiri\ThemeTools\Console\Commands\ThemeUpdateCommand;

final class ThemeToolsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'theme-tools');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            AddThemeCommand::class,
            DefaultThemeCommand::class,
            ThemeDoctorCommand::class,
            ImportThemeCommand::class,
            RemoveThemeCommand::class,
            ThemeSetupCommand::class,
            ThemeUpdateCommand::class,
        ]);
    }
}
