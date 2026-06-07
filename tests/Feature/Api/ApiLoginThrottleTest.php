<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('throttles repeated login attempts after five tries', function () {
    $user = User::factory()->create([
        'email' => 'throttle@example.com',
        'password' => 'secret-password',
    ]);
    $user->assignRole(UserRole::AGENT->value);

    // The first five attempts reach the controller (401 for bad credentials).
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    // The sixth attempt is blocked by the rate limiter before the controller runs.
    $this->postJson('/api/login', [
        'email' => 'throttle@example.com',
        'password' => 'secret-password',
    ])->assertStatus(429);
});

it('does not throttle a single successful login', function () {
    $user = User::factory()->create([
        'email' => 'fresh@example.com',
        'password' => 'secret-password',
    ]);
    $user->assignRole(UserRole::AGENT->value);

    $this->postJson('/api/login', [
        'email' => 'fresh@example.com',
        'password' => 'secret-password',
    ])->assertOk();
});
