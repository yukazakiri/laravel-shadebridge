<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

final class ThemeDoctorCommand extends Command
{
    protected $signature = 'theme:doctor';

    protected $description = 'Check whether theme tooling is installed correctly';

    public function handle(): int
    {
        note('Checking Shadebridge for Laravel setup...');
        note('');

        $criticalChecks = [
            'resources/css/app.css' => File::exists(resource_path('css/app.css')),
            'resources/js/conf/themes.ts' => File::exists(resource_path('js/conf/themes.ts')),
        ];

        foreach ($criticalChecks as $label => $passed) {
            $this->report($label, $passed);
        }

        $this->report('resources/css/themes directory', File::isDirectory(resource_path('css/themes')));

        $packageJson = base_path('package.json');
        if (File::exists($packageJson)) {
            $packages = $this->readPackageNames($packageJson);

            $this->report('Tailwind CSS dependency', in_array('tailwindcss', $packages, true));
            $this->report('Inertia React or Vue dependency', in_array('@inertiajs/react', $packages, true) || in_array('@inertiajs/vue3', $packages, true));
            $this->report('Lucide icon dependency', in_array('lucide-react', $packages, true) || in_array('lucide-vue-next', $packages, true));
        } else {
            warning('  - package.json not found, skipping frontend dependency checks.');
        }

        if (in_array(false, $criticalChecks, true)) {
            warning('Theme setup is incomplete. Run <comment>php artisan theme:setup</comment> to install the required files.');

            return self::FAILURE;
        }

        info('Theme tooling looks ready.');

        return self::SUCCESS;
    }

    private function report(string $label, bool $passed): void
    {
        if ($passed) {
            info('  ✓ '.$label);

            return;
        }

        warning('  - '.$label.' missing');
    }

    /**
     * @return array<int, string>
     */
    private function readPackageNames(string $packageJson): array
    {
        $contents = json_decode(File::get($packageJson), true);

        if (! is_array($contents)) {
            return [];
        }

        $dependencies = array_merge(
            is_array($contents['dependencies'] ?? null) ? $contents['dependencies'] : [],
            is_array($contents['devDependencies'] ?? null) ? $contents['devDependencies'] : [],
        );

        return array_keys($dependencies);
    }
}
