<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('backoffice.dashboard'))
        ->assertRedirect(route('login'));
});

test('agents cannot access the backoffice', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get(route('backoffice.dashboard'))
        ->assertForbidden();
});

test('admins can access the backoffice', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertOk();
});

test('supervisors can access the backoffice', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->get(route('backoffice.dashboard'))
        ->assertOk();
});

test('users without any role cannot access the backoffice', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backoffice.dashboard'))
        ->assertForbidden();
});

test('the /backoffice index redirects to the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice')
        ->assertRedirect('/backoffice/dashboard');
});
