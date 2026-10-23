<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge\Console\Commands;

use Illuminate\Console\Command;

/** Backward-compatible entry point; both commands use the same importer. */
final class AddThemeCommand extends Command
{
    protected $signature = 'theme:add {url? : Theme registry JSON URL} {--name=} {--description=} {--force}';

    protected $description = 'Import a theme from a shadcn registry (alias for theme:import)';

    public function handle(): int
    {
        return $this->call('theme:import', array_filter([
            'url' => $this->argument('url'),
            '--name' => $this->option('name'),
            '--description' => $this->option('description'),
            '--force' => $this->option('force'),
        ], fn ($value): bool => $value !== null));
    }
}
