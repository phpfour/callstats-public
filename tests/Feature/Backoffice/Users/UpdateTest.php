<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('updates a user without touching the password when blank', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->agent()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => Hash::make('original-password'),
    ]);

    $this->actingAs($admin)->put("/backoffice/users/{$target->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'role' => UserRole::AGENT->value,
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect('/backoffice/users');

    $fresh = $target->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->email)->toBe('new@example.com')
        ->and(Hash::check('original-password', $fresh->password))->toBeTrue();
});

it('changes the password when one is supplied', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->agent()->create();

    $this->actingAs($admin)->put("/backoffice/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::AGENT->value,
        'password' => 'BrandNew123!',
        'password_confirmation' => 'BrandNew123!',
    ])->assertRedirect();

    expect(Hash::check('BrandNew123!', $target->fresh()->password))->toBeTrue();
});

it('changes the role on update', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->agent()->create();

    $this->actingAs($admin)->put("/backoffice/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::SUPERVISOR->value,
    ])->assertRedirect();

    expect($target->fresh()->hasRole(UserRole::SUPERVISOR->value))->toBeTrue()
        ->and($target->fresh()->hasRole(UserRole::AGENT->value))->toBeFalse();
});

it('allows the same email for the user being edited', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->agent()->create(['email' => 'keep@example.com']);

    $this->actingAs($admin)->put("/backoffice/users/{$target->id}", [
        'name' => $target->name,
        'email' => 'keep@example.com',
        'role' => UserRole::AGENT->value,
    ])->assertRedirect('/backoffice/users');
});

it('refuses to demote the last admin', function () {
    $onlyAdmin = User::factory()->admin()->create();

    $this->actingAs($onlyAdmin)->put("/backoffice/users/{$onlyAdmin->id}", [
        'name' => $onlyAdmin->name,
        'email' => $onlyAdmin->email,
        'role' => UserRole::SUPERVISOR->value,
    ])->assertSessionHasErrors('role');

    expect($onlyAdmin->fresh()->hasRole(UserRole::ADMIN->value))->toBeTrue();
});

it('allows demoting an admin when another admin exists', function () {
    $admin = User::factory()->admin()->create();
    $secondAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->put("/backoffice/users/{$secondAdmin->id}", [
        'name' => $secondAdmin->name,
        'email' => $secondAdmin->email,
        'role' => UserRole::SUPERVISOR->value,
    ])->assertRedirect('/backoffice/users');

    expect($secondAdmin->fresh()->hasRole(UserRole::SUPERVISOR->value))->toBeTrue();
});

it('forbids supervisors from updating users', function () {
    $supervisor = User::factory()->supervisor()->create();
    $target = User::factory()->agent()->create();

    $this->actingAs($supervisor)
        ->put("/backoffice/users/{$target->id}", [
            'name' => 'Hijacked',
            'email' => $target->email,
            'role' => UserRole::AGENT->value,
        ])
        ->assertForbidden();
});
