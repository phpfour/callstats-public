<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns leads assigned to the authed agent ordered by assigned_at desc with the lastCall relation', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $other = User::factory()->create();

    $newest = Lead::factory()->create([
        'assigned_to_id' => $agent->id,
        'assigned_at' => now()->subDay(),
    ]);
    $older = Lead::factory()->create([
        'assigned_to_id' => $agent->id,
        'assigned_at' => now()->subWeek(),
    ]);
    $unassigned = Lead::factory()->create([
        'assigned_to_id' => $other->id,
        'assigned_at' => now()->subHour(),
    ]);

    CallLog::factory()->create([
        'lead_id' => $newest->id,
        'user_id' => $agent->id,
        'called_at' => now()->subHour(),
        'outcome' => 'Successful Contact',
    ]);

    Sanctum::actingAs($agent);

    $response = $this->getJson('/api/leads');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('lead_id')->all();
    expect($ids)->toBe([$newest->id, $older->id]);
    expect($ids)->not->toContain($unassigned->id);

    $first = $response->json('data.0');
    expect($first['last_call'])->toMatchArray([
        'outcome' => 'Successful Contact',
    ]);

    $fixture = apiFixture('leads.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('returns an empty list when the authed agent has no leads', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    Sanctum::actingAs($agent);

    $response = $this->getJson('/api/leads');

    $response->assertOk()->assertExactJson(['data' => []]);
});

it('rejects unauthenticated lead requests', function () {
    $this->getJson('/api/leads')->assertUnauthorized();
});
