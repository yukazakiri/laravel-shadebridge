<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('adds a theme from a registry json url', function () {
    $this->writeResourceFile('css/app.css', <<<'CSS'
@import 'tailwindcss';

@source '../views';
CSS);

    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));

    Http::fake([
        'https://example.com/themes/midnight.json' => Http::response([
            'name' => 'Midnight Blue',
            'cssVars' => [
                'theme' => [
                    'font-sans' => 'Inter, sans-serif',
                ],
                'light' => [
                    'background' => 'oklch(1 0 0)',
                    'primary' => 'oklch(0.45 0.12 250)',
                    'secondary' => 'oklch(0.88 0.05 250)',
                    'accent' => 'oklch(0.82 0.07 250)',
                ],
                'dark' => [
                    'background' => 'oklch(0.12 0.02 250)',
                    'primary' => 'oklch(0.72 0.12 250)',
                ],
            ],
        ]),
    ]);

    $this->artisan('theme:add', ['url' => 'https://example.com/themes/midnight.json'])
        ->assertSuccessful();

    expect(File::exists(resource_path('css/themes/midnight-blue.css')))->toBeTrue();

    $this->assertResourceFileContains('css/themes/midnight-blue.css', '.theme-midnight-blue');
    $this->assertResourceFileContains('css/themes/midnight-blue.css', '--primary: oklch(0.45 0.12 250);');
    $this->assertResourceFileContains('css/app.css', '@import "./themes/midnight-blue.css";');
    $this->assertResourceFileContains('css/themes/midnight-blue.css', "@import url('https://fonts.googleapis.com/css2?family=Inter&display=swap');");
    $this->assertResourceFileContains('js/conf/themes.ts', "| 'midnight-blue'");
    $this->assertResourceFileContains('js/conf/themes.ts', "id: 'midnight-blue'");
});

it('shows a setup hint when theme files are missing', function () {
    $this->artisan('theme:add', ['url' => 'https://example.com/themes/midnight.json'])
        ->assertFailed();
});

it('rejects invalid theme urls', function () {
    $this->artisan('theme:add', ['url' => 'not-a-url'])
        ->assertFailed();
});
