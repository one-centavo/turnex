<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;

test('application trusts reverse proxies and detects https scheme from forwarded proto header', function (): void {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ])->get('/up');

    $response->assertOk();
    expect(request()->isSecure())->toBeTrue();
});

test('url generator forces https scheme', function (): void {
    URL::forceScheme('https');

    expect(url('/test-path'))->toStartWith('https://');
});
