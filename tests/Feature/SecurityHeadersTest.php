<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Vite;

it('sends baseline security headers on web responses', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('backoffice.dashboard'));

    $response->assertOk();
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');

    // Framing/object protections apply in every environment.
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'");
});

it('does not emit asset-fetch directives while the vite dev server is running', function () {
    Vite::partialMock()->shouldReceive('isRunningHot')->andReturnTrue();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('backoffice.dashboard'));

    // No default-src/script-src lockdown in dev — those would block the Vite
    // dev server origin, which CSP host-source syntax cannot allow-list.
    expect($response->headers->get('Content-Security-Policy'))
        ->not->toContain('default-src')
        ->not->toContain('script-src');
});

it('locks down asset origins for a built deployment', function () {
    Vite::partialMock()->shouldReceive('isRunningHot')->andReturnFalse();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('backoffice.dashboard'));

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("connect-src 'self'");
});

it('does not send HSTS over plain http', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('backoffice.dashboard'));

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('restricts cors to an explicit origin allow-list rather than a wildcard', function () {
    expect(config('cors.allowed_origins'))->not->toContain('*')
        ->and(config('cors.supports_credentials'))->toBeFalse();
});
