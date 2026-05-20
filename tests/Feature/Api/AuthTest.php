<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('logs an Agent in and returns a Sanctum token plus the public user fields', function () {
    $user = User::factory()->create([
        'email' => 'agent@example.com',
        'password' => 'secret-password',
    ]);
    $user->assignRole(UserRole::AGENT->value);

    $response = $this->postJson('/api/login', [
        'email' => 'agent@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        ]);

    expect($response->json('token'))->toBeString()->not->toBe('');
    expect($response->json('user.id'))->toBe($user->id);
    expect($response->json('user.email'))->toBe('agent@example.com');

    $fixture = apiFixture('login.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('rejects bad credentials with 401', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $user->assignRole(UserRole::AGENT->value);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()
        ->assertExactJson(['message' => 'Invalid credentials']);
});

it('rejects login for users that lack the agent role with 403', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $user->assignRole(UserRole::ADMIN->value);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertForbidden()
        ->assertExactJson(['message' => 'User is not an Agent']);
});

it('validates that login requires an email and password', function () {
    $this->postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns the authenticated user from /api/me with a Sanctum token', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::AGENT->value);

    $token = $user->createToken('mobile')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->getJson('/api/me');

    $response->assertOk()->assertJson([
        'id' => $user->id,
        'email' => $user->email,
    ]);

    $fixture = apiFixture('me.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('rejects /api/me without a token', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
