<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

final class ThemeUpdateCommand extends Command
{
    protected $signature = 'theme:update
                            {--stack= : Frontend stack: react or vue}
                            {--force : Force update without confirmation}';

    protected $description = 'Update the theme personalization components';

    public function handle(): int
    {
        $stack = $this->option('stack');

        if ($stack !== null) {
            $stack = (string) $stack;

            if (! in_array($stack, ['react', 'vue'], true)) {
                error(sprintf('Invalid --stack value "%s". Expected react or vue.', $stack));

                return self::FAILURE;
            }
        }

        $stack ??= $this->detectStack();

        if ($stack === null) {
            $stack = select(
                label: 'Which stack are you using?',
                options: [
                    'react' => 'Inertia React',
                    'vue' => 'Inertia Vue',
                ],
                default: 'react',
                hint: "We couldn't auto-detect your stack"
            );
        }

        if (! $this->option('force') && ! confirm('This will overwrite your theme personalization components. Do you wish to continue?', false)) {
            info('Update cancelled.');

            return self::SUCCESS;
        }

        if ($stack === 'react') {
            $stubPath = __DIR__.'/../../../stubs/react/resources/js/components/theme-switcher.tsx';
            $destPath = resource_path('js/components/theme-switcher.tsx');
        } else {
            $stubPath = __DIR__.'/../../../stubs/vue/resources/js/components/ThemeSwitcher.vue';
            $destPath = resource_path('js/components/ThemeSwitcher.vue');
        }

        if (! File::exists($stubPath)) {
            error('Theme stub not found at: '.$stubPath);

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($destPath));
        File::copy($stubPath, $destPath);
        info('  ✓ Updated '.basename($destPath));

        note('');
        info('Theme components updated successfully!');
        note('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the changes.');

        return self::SUCCESS;
    }

    private function detectStack(): ?string
    {
        if (File::exists(resource_path('js/components/theme-switcher.tsx'))) {
            return 'react';
        }

        if (File::exists(resource_path('js/components/ThemeSwitcher.vue'))) {
            return 'vue';
        }

        if (File::exists(resource_path('js/app.tsx'))) {
            return 'react';
        }

        if (File::exists(resource_path('js/app.ts')) && (File::exists(resource_path('js/Pages')) || File::exists(resource_path('js/pages')))) {
            return 'vue';
        }

        $packageJson = base_path('package.json');
        if (! File::exists($packageJson)) {
            return null;
        }

        $contents = json_decode(File::get($packageJson), true);

        if (! is_array($contents)) {
            return null;
        }

        $dependencies = array_merge(
            is_array($contents['dependencies'] ?? null) ? $contents['dependencies'] : [],
            is_array($contents['devDependencies'] ?? null) ? $contents['devDependencies'] : [],
        );

        if (array_key_exists('@inertiajs/react', $dependencies)) {
            return 'react';
        }

        if (array_key_exists('@inertiajs/vue3', $dependencies)) {
            return 'vue';
        }

        return null;
    }
}
