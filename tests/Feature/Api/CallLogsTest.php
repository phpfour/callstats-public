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

it('stores a call log and returns the resource shape', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $payload = [
        'lead_id' => $lead->id,
        'called_at' => '2026-05-06 12:28:43',
        'duration' => 89,
        'notes' => 'Successful contact',
        'outcome' => 'Successful Contact',
    ];

    $response = $this->postJson('/api/call-logs', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.lead_id', $lead->id)
        ->assertJsonPath('data.user_id', $agent->id)
        ->assertJsonPath('data.duration', 89)
        ->assertJsonPath('data.duration_hms', '00:01:29')
        ->assertJsonPath('data.outcome', 'Successful Contact')
        ->assertJsonPath('data.callback_at', null)
        ->assertJsonPath('data.called_at', '2026-05-06 12:28:43');

    $this->assertDatabaseHas('call_logs', [
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'outcome' => 'Successful Contact',
        'duration' => 89,
    ]);

    $fixture = apiFixture('call-logs-store.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('creates a callback reminder side-effect when callback_at is provided', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $response = $this->postJson('/api/call-logs', [
        'lead_id' => $lead->id,
        'called_at' => '2026-05-06 12:28:43',
        'duration' => 60,
        'notes' => 'Wants a callback later today',
        'outcome' => 'Call Back Requested',
        'callback_at' => '2026-05-06 16:00:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.callback_at', '2026-05-06 16:00:00');

    $callLogId = $response->json('data.call_log_id');

    $reminder = Reminder::where('call_log_id', $callLogId)->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->type)->toBe('callback');
    expect($reminder->lead_id)->toBe($lead->id);
    expect($reminder->user_id)->toBe($agent->id);
    expect($reminder->notes)->toBe('Wants a callback later today');
});

it('does not create a reminder when callback_at is omitted', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/call-logs', [
        'lead_id' => $lead->id,
        'called_at' => '2026-05-06 12:28:43',
        'outcome' => 'No Answer',
    ])->assertCreated();

    expect(Reminder::count())->toBe(0);
});

it('rejects an outcome that is not in the configured list', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $lead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/call-logs', [
        'lead_id' => $lead->id,
        'called_at' => '2026-05-06 12:28:43',
        'outcome' => 'Made Up Outcome',
    ])->assertStatus(422)->assertJsonValidationErrors(['outcome']);
});

it('lists only the agents own call logs and ignores user_id query param', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);
    $other = User::factory()->create();
    $other->assignRole(UserRole::AGENT->value);

    $lead = Lead::factory()->create();

    CallLog::factory()->create(['lead_id' => $lead->id, 'user_id' => $agent->id, 'called_at' => now()->subHours(2)]);
    CallLog::factory()->create(['lead_id' => $lead->id, 'user_id' => $other->id, 'called_at' => now()->subHour()]);

    Sanctum::actingAs($agent);

    // Even when user_id is passed, an agent only ever sees their own calls.
    $response = $this->getJson("/api/call-logs?user_id={$other->id}");

    $response->assertOk();
    $userIds = collect($response->json('data'))->pluck('user_id')->unique()->values()->all();
    expect($userIds)->toBe([$agent->id]);

    $fixture = apiFixture('call-logs-index.json');
    assertJsonShapeMatches($fixture, $response->json());
});

it('lets non-agents filter by user_id, lead_id, outcome and date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN->value);

    $agentOne = User::factory()->create();
    $agentTwo = User::factory()->create();

    $leadOne = Lead::factory()->create();
    $leadTwo = Lead::factory()->create();

    $kept = CallLog::factory()->create([
        'lead_id' => $leadOne->id,
        'user_id' => $agentOne->id,
        'called_at' => '2026-05-06 10:00:00',
        'outcome' => 'Successful Contact',
    ]);
    CallLog::factory()->create([
        'lead_id' => $leadTwo->id,
        'user_id' => $agentTwo->id,
        'called_at' => '2026-05-06 10:00:00',
        'outcome' => 'Successful Contact',
    ]);
    CallLog::factory()->create([
        'lead_id' => $leadOne->id,
        'user_id' => $agentOne->id,
        'called_at' => '2026-05-06 10:00:00',
        'outcome' => 'No Answer',
    ]);
    CallLog::factory()->create([
        'lead_id' => $leadOne->id,
        'user_id' => $agentOne->id,
        'called_at' => '2026-04-01 10:00:00',
        'outcome' => 'Successful Contact',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson(sprintf(
        '/api/call-logs?user_id=%d&lead_id=%d&outcome=%s&from=%s&to=%s',
        $agentOne->id,
        $leadOne->id,
        urlencode('Successful Contact'),
        '2026-05-01',
        '2026-05-31',
    ));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('call_log_id')->all();
    expect($ids)->toBe([$kept->id]);
});

it('rejects unauthenticated call log requests', function () {
    $this->postJson('/api/call-logs', [])->assertUnauthorized();
    $this->getJson('/api/call-logs')->assertUnauthorized();
});
