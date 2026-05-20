<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user and assigns the chosen role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'code' => 'A-100',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect('/backoffice/users')
        ->assertSessionHas('success');

    $user = User::where('email', 'aisha@example.com')->firstOrFail();

    expect($user->name)->toBe('Aisha Khan')
        ->and($user->code)->toBe('A-100')
        ->and($user->hasRole(UserRole::AGENT->value))->toBeTrue()
        ->and(Hash::check('Password123!', $user->password))->toBeTrue();
});

it('rejects duplicate email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Conflict',
            'email' => 'taken@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects duplicate agent code', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['code' => 'A-007']);

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Conflict',
            'email' => 'fresh@example.com',
            'code' => 'A-007',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertSessionHasErrors('code');
});

it('requires a confirmed password on create', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Different!',
        ])
        ->assertSessionHasErrors('password');
});

it('rejects an unknown role value', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'role' => 'sysop',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertSessionHasErrors('role');
});

it('rejects supervisors from creating users', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->post('/backoffice/users', [
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertForbidden();
});
