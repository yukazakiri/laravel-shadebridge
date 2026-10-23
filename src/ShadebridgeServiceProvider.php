<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge;

use Illuminate\Support\ServiceProvider;
use Yukazakiri\Shadebridge\Console\Commands\AddThemeCommand;
use Yukazakiri\Shadebridge\Console\Commands\DefaultThemeCommand;
use Yukazakiri\Shadebridge\Console\Commands\ImportThemeCommand;
use Yukazakiri\Shadebridge\Console\Commands\RemoveThemeCommand;
use Yukazakiri\Shadebridge\Console\Commands\ThemeDoctorCommand;
use Yukazakiri\Shadebridge\Console\Commands\ThemeSetupCommand;
use Yukazakiri\Shadebridge\Console\Commands\ThemeUpdateCommand;

final class ShadebridgeServiceProvider extends ServiceProvider
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
