<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;

it('renders the leads index for an admin', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/backoffice/leads')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/leads/index')
            ->has('leads.data', 3)
            ->where('sort.column', null)
            ->where('sort.direction', 'desc'));
});

it('rejects an agent attempting to view the backoffice leads list', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/leads')
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get('/backoffice/leads')->assertRedirect('/login');
});

it('filters leads by search term across name and phone', function () {
    $admin = User::factory()->admin()->create();

    Lead::factory()->create(['name' => 'Alice Wonderland', 'phone_number' => '+8801711111111']);
    Lead::factory()->create(['name' => 'Bob Builder', 'phone_number' => '+8801722222222']);
    Lead::factory()->create(['name' => 'Charlie Day', 'phone_number' => '+8801711999999']);

    $this->actingAs($admin)
        ->get('/backoffice/leads?search=Alice')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));

    $this->actingAs($admin)
        ->get('/backoffice/leads?search=11111')
        ->assertInertia(fn ($page) => $page->has('leads.data', 1));
});

it('filters leads by assignee', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();

    Lead::factory()->count(2)->create(['assigned_to_id' => $assignee->id]);
    Lead::factory()->create();

    $this->actingAs($admin)
        ->get("/backoffice/leads?assigned_to_id={$assignee->id}")
        ->assertInertia(fn ($page) => $page->has('leads.data', 2));
});

it('paginates leads at 25 per page', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->count(30)->create();

    $this->actingAs($admin)
        ->get('/backoffice/leads')
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 25)
            ->where('leads.per_page', 25)
            ->where('leads.total', 30));
});

it('honors a valid sort column and direction', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->create(['name' => 'Bravo']);
    Lead::factory()->create(['name' => 'Alfa']);
    Lead::factory()->create(['name' => 'Charlie']);

    $response = $this->actingAs($admin)
        ->get('/backoffice/leads?sort=name&dir=asc')
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('sort.column', 'name')
        ->where('sort.direction', 'asc')
        ->where('leads.data.0.name', 'Alfa')
        ->where('leads.data.2.name', 'Charlie'));
});

it('falls back to assigned_at desc for an unknown sort column', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/backoffice/leads?sort=DROP+TABLE&dir=asc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('leads.data', 2));
});
