<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('stores a reminder linked to a lead and returns the resource shape', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $response = $this->postJson('/api/reminders', [
        'lead_id' => $lead->id,
        'remind_at' => '2026-03-01 12:53:00',
        'notes' => 'Future intake follow-up',
        'type' => 'Follow-up',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.lead.lead_id', $lead->id)
        ->assertJsonPath('data.user_id', $agent->id)
        ->assertJsonPath('data.remind_at', '2026-03-01 12:53:00')
        ->assertJsonPath('data.type', 'Follow-up')
        ->assertJsonPath('data.call_log', null);

    $this->assertDatabaseHas('reminders', [
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'type' => 'Follow-up',
    ]);

    $fixture = apiFixture('reminders-store.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('stores a reminder linked to a call log when call_log_id is provided', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create();
    $callLog = CallLog::factory()->create(['lead_id' => $lead->id, 'user_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $response = $this->postJson('/api/reminders', [
        'lead_id' => $lead->id,
        'call_log_id' => $callLog->id,
        'remind_at' => '2026-06-01 09:00:00',
        'type' => 'callback',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.call_log.call_log_id', $callLog->id);
});

it('rejects remind_at that is not the strict Y-m-d H:i:s format', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/reminders', [
        'lead_id' => $lead->id,
        'remind_at' => '2026-03-01',
    ])->assertStatus(422)->assertJsonValidationErrors(['remind_at']);
});

it('lists reminders only for the authed user ordered by remind_at desc', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $other = User::factory()->create();

    $lead = Lead::factory()->create();

    $newer = Reminder::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'remind_at' => '2026-06-01 09:00:00',
    ]);
    $older = Reminder::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'remind_at' => '2026-05-01 09:00:00',
    ]);
    Reminder::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $other->id,
        'remind_at' => '2026-07-01 09:00:00',
    ]);

    Sanctum::actingAs($agent);

    $response = $this->getJson('/api/reminders');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('reminder_id')->all();
    expect($ids)->toBe([$newer->id, $older->id]);

    $fixture = apiFixture('reminders-index.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('filters reminders by lead_id', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    $kept = Reminder::factory()->create(['lead_id' => $leadA->id, 'user_id' => $agent->id]);
    Reminder::factory()->create(['lead_id' => $leadB->id, 'user_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $response = $this->getJson("/api/reminders?lead_id={$leadA->id}");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('reminder_id')->all();
    expect($ids)->toBe([$kept->id]);
});

it('rejects unauthenticated reminder requests', function () {
    $this->postJson('/api/reminders', [])->assertUnauthorized();
    $this->getJson('/api/reminders')->assertUnauthorized();
});
