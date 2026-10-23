<?php

use Illuminate\Support\Facades\File;

it('sets and resets the system default and rejects unknown IDs', function () {
    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));
    $this->artisan('theme:default', ['theme' => 'ocean'])->assertSuccessful();
    $this->assertResourceFileContains('js/conf/themes.ts', "defaultColorTheme: ColorTheme = 'ocean'");
    $this->artisan('theme:default', ['theme' => 'unknown'])->assertFailed();
    $this->artisan('theme:default', ['--reset' => true])->assertSuccessful();
    $this->assertResourceFileContains('js/conf/themes.ts', "defaultColorTheme: ColorTheme = 'default'");
});

it('resets the default when its catalog-only theme is removed', function () {
    $this->writeResourceFile('css/app.css', '');
    $this->writeResourceFile('js/conf/themes.ts', File::get($this->packagePath('stubs/react/resources/js/conf/themes.ts')));
    $this->artisan('theme:default', ['theme' => 'ocean'])->assertSuccessful();
    $this->artisan('theme:remove', ['theme' => 'ocean', '--force' => true])->assertSuccessful();
    $this->assertResourceFileContains('js/conf/themes.ts', "defaultColorTheme: ColorTheme = 'default'");
    expect(File::get(resource_path('js/conf/themes.ts')))->not->toContain("id: 'ocean'");
});

it('rejects removal paths outside the themes directory', function () {
    $this->artisan('theme:remove', ['theme' => '../app', '--force' => true])->assertFailed();
});
