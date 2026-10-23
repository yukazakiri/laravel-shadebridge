<?php

it('lets Composer infer release versions from Git tags', function () {
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($metadata)->not->toHaveKey('version')
        ->and($metadata['name'])->toBe('yukazakiri/laravel-shadebridge');
});
