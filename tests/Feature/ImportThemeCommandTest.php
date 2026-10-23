<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('imports a tweakcn theme into the generated theme config', function () {
    $this->writeResourceFile('css/app.css', <<<'CSS'
@import 'tailwindcss';
@import 'tw-animate-css';

@source '../views';
CSS);

    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/vue/resources/js/conf/themes.ts')));

    Http::fake([
        'https://tweakcn.com/r/themes/vintage-paper.json' => Http::response([
            'name' => 'Vintage Paper',
            'cssVars' => [
                'theme' => [
                    'font-sans' => 'Merriweather, serif',
                ],
                'light' => [
                    'primary' => 'oklch(0.44 0.08 80)',
                    'secondary' => 'oklch(0.92 0.03 85)',
                    'accent' => 'oklch(0.86 0.05 75)',
                ],
                'dark' => [
                    'primary' => 'oklch(0.72 0.08 80)',
                ],
            ],
        ]),
    ]);

    $this->artisan('theme:import', ['url' => 'https://tweakcn.com/r/themes/vintage-paper.json'])
        ->assertSuccessful();

    expect(File::exists(resource_path('css/themes/vintage-paper.css')))->toBeTrue();

    $this->assertResourceFileContains('css/app.css', '@import "./themes/vintage-paper.css";');
    $this->assertResourceFileContains('js/conf/themes.ts', "| 'vintage-paper'");
    $this->assertResourceFileContains('js/conf/themes.ts', "id: 'vintage-paper'");
    $this->assertResourceFileContains('js/conf/themes.ts', "font: 'Merriweather'");
});

it('shows a setup hint before importing when theme files are missing', function () {
    $this->artisan('theme:import', ['url' => 'https://tweakcn.com/r/themes/vintage-paper.json'])
        ->assertFailed();
});

it('rejects malformed registry data without changing source files', function (mixed $data) {
    $this->writeResourceFile('css/app.css', "@import 'tailwindcss';");
    $catalog = File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts'));
    $this->writeResourceFile('js/conf/themes.ts', $catalog);
    Http::fake(['*' => Http::response($data)]);

    $this->artisan('theme:import', ['url' => 'https://example.com/theme.json'])->assertFailed();

    expect(File::get(resource_path('js/conf/themes.ts')))->toBe($catalog)
        ->and(File::get(resource_path('css/app.css')))->toBe("@import 'tailwindcss';")
        ->and(File::exists(resource_path('css/themes')))->toBeFalse();
})->with([
    'missing variables' => [[]],
    'scalar json' => ['oops'],
    'invalid section' => [['cssVars' => ['light' => ['primary' => 'red'], 'dark' => 'invalid']]],
    'declaration injection' => [['cssVars' => ['light' => ['primary' => 'red; } body { display:none']]]],
    'remote css' => [['cssVars' => ['light' => ['primary' => 'url(https://example.com)']]]],
]);

it('preserves shared tokens and updates metadata on forced reimport', function () {
    $this->writeResourceFile('css/app.css', "/* Unicode: café */\n@import 'tailwindcss';");
    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));
    $data = ['name' => 'Kodama Grove', 'cssVars' => [
        'theme' => ['radius' => '0.425rem', 'font-sans' => 'var(--app-font)'],
        'light' => ['primary' => 'red'],
        'dark' => ['primary' => 'blue'],
    ], 'css' => ['@layer base' => ['body, p' => ['letter-spacing' => 'var(--tracking-normal)']]]];
    Http::fake(['*' => Http::response($data)]);
    $this->artisan('theme:import', ['url' => 'https://example.com/theme.json'])->assertSuccessful();
    $this->artisan('theme:import', ['url' => 'https://example.com/theme.json', '--force' => true, '--description' => 'Updated'])->assertSuccessful();

    $css = File::get(resource_path('css/themes/kodama-grove.css'));
    expect(substr_count($css, '--radius: 0.425rem;'))->toBe(2)
        ->and($css)->toContain('.theme-kodama-grove body, .theme-kodama-grove p')
        ->and($css)->not->toContain('fonts.googleapis.com')
        ->and(substr_count(File::get(resource_path('css/app.css')), './themes/kodama-grove.css'))->toBe(1);
    $this->assertResourceFileContains('js/conf/themes.ts', "description: 'Updated'");
});

it('handles HTTP failures without generating a theme', function () {
    $this->writeResourceFile('css/app.css', '');
    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));
    Http::fake(['*' => Http::response(['cssVars' => ['light' => ['primary' => 'red']]], 500)]);
    $this->artisan('theme:import', ['url' => 'https://example.com/theme.json'])->assertFailed();
    expect(File::exists(resource_path('css/themes')))->toBeFalse();
});

it('imports the actual Kodama Grove registry fixture', function () {
    $this->writeResourceFile('css/app.css', "@import 'tailwindcss';");
    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));
    $data = json_decode(File::get($this->packagePath('tests/Fixtures/kodama-grove.json')), true, 512, JSON_THROW_ON_ERROR);
    Http::fake(['*' => Http::response($data)]);
    $this->artisan('theme:import', ['url' => 'https://tweakcn.com/r/themes/kodama-grove.json'])->assertSuccessful();
    $this->artisan('theme:default', ['theme' => 'kodama-grove'])->assertSuccessful();
    $this->assertResourceFileContains('css/themes/kodama-grove.css', '--radius: 0.425rem;');
    $this->assertResourceFileContains('css/themes/kodama-grove.css', '.theme-kodama-grove body');
    $this->assertResourceFileContains('js/conf/themes.ts', "font: 'Merriweather'");
    $html = view('theme-tools::initialize', ['storageKey' => 'ui-color-theme'])->render();
    expect($html)->toContain('kodama-grove', 'ui-color-theme');
});
