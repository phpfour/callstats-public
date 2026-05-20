<?php

declare(strict_types=1);

use App\Models\User;

it('issues a Sanctum token that authenticates against the api guard', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/me');

    $response->assertOk()->assertJson(['id' => $user->id, 'email' => $user->email]);
});

it('rejects requests without a Sanctum token', function () {
    $response = $this->getJson('/api/me');

    $response->assertUnauthorized();
});

it('rejects requests with an invalid Sanctum token', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer not-a-real-token',
        'Accept' => 'application/json',
    ])->getJson('/api/me');

    $response->assertUnauthorized();
});
