<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('renders the users index for an admin', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/backoffice/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/users/index')
            ->has('users.data')
            ->has('roles', count(UserRole::cases())));
});

it('forbids supervisors from the users index', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get('/backoffice/users')
        ->assertForbidden();
});

it('forbids agents from the users index', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/users')
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get('/backoffice/users')->assertRedirect('/login');
});

it('searches across name, email, and code', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Aisha Khan', 'email' => 'aisha@example.com']);
    User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com', 'code' => 'A-007']);
    User::factory()->create(['name' => 'Charlie Day', 'email' => 'charlie@example.com']);

    $this->actingAs($admin)
        ->get('/backoffice/users?search=Aisha')
        ->assertInertia(fn ($page) => $page->where('users.data.0.name', 'Aisha Khan'));

    $this->actingAs($admin)
        ->get('/backoffice/users?search=A-007')
        ->assertInertia(fn ($page) => $page->where('users.data.0.code', 'A-007'));
});

it('filters by role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->agent()->create();
    User::factory()->supervisor()->create();

    $this->actingAs($admin)
        ->get('/backoffice/users?role='.UserRole::AGENT->value)
        ->assertInertia(fn ($page) => $page->has('users.data', 2));
});
