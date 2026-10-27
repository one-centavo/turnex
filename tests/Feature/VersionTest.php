<?php

declare(strict_types=1);

test('application version resolves semver format', function (): void {
    $version = config('app.version');

    expect($version)->toBeString()
        ->and($version)->not->toBeEmpty()
        ->and($version)->toMatch('/^v\d+\.\d+\.\d+/');
});
