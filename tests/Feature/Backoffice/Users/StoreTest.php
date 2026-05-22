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

it('persists KPI targets when role is agent', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'KPI Agent',
            'email' => 'kpi@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'daily_call_target' => 30,
            'conversion_rate_target' => 45,
        ])
        ->assertRedirect('/backoffice/users');

    $user = User::where('email', 'kpi@example.com')->firstOrFail();

    expect($user->kpiTarget)->not->toBeNull()
        ->and($user->kpiTarget->daily_call_target)->toBe(30)
        ->and($user->kpiTarget->conversion_rate_target)->toBe(45);
});

it('creates a KPI row with null targets when none are supplied for an agent', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'No Targets',
            'email' => 'no-targets@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect('/backoffice/users');

    $user = User::where('email', 'no-targets@example.com')->firstOrFail();

    expect($user->kpiTarget)->not->toBeNull()
        ->and($user->kpiTarget->daily_call_target)->toBeNull()
        ->and($user->kpiTarget->conversion_rate_target)->toBeNull();
});

it('rejects a negative daily call target', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Bad KPI',
            'email' => 'bad-kpi@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'daily_call_target' => -1,
            'conversion_rate_target' => 10,
        ])
        ->assertSessionHasErrors('daily_call_target');
});

it('rejects a conversion rate target above 100', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Bad KPI',
            'email' => 'bad-kpi@example.com',
            'role' => UserRole::AGENT->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'daily_call_target' => 10,
            'conversion_rate_target' => 200,
        ])
        ->assertSessionHasErrors('conversion_rate_target');
});

it('ignores KPI inputs when the role is not agent', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/users', [
            'name' => 'Supervisor User',
            'email' => 'sup@example.com',
            'role' => UserRole::SUPERVISOR->value,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'daily_call_target' => null,
            'conversion_rate_target' => null,
        ])
        ->assertRedirect('/backoffice/users');

    $user = User::where('email', 'sup@example.com')->firstOrFail();

    expect($user->kpiTarget)->toBeNull();
});
