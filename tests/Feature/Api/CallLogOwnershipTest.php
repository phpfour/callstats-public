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

it('rejects logging a call against a lead assigned to another agent', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $otherAgent = User::factory()->create();
    $otherAgent->assignRole(UserRole::AGENT->value);

    $foreignLead = Lead::factory()->create(['assigned_to_id' => $otherAgent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/call-logs', [
        'lead_id' => $foreignLead->id,
        'called_at' => '2026-05-06 12:28:43',
        'outcome' => 'No Answer',
    ])->assertStatus(422)->assertJsonValidationErrors(['lead_id']);

    expect(CallLog::count())->toBe(0);
});

it('rejects logging a call against an unassigned lead', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $unassignedLead = Lead::factory()->create(['assigned_to_id' => null]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/call-logs', [
        'lead_id' => $unassignedLead->id,
        'called_at' => '2026-05-06 12:28:43',
        'outcome' => 'No Answer',
    ])->assertStatus(422)->assertJsonValidationErrors(['lead_id']);
});

it('allows logging a call against the agents own assigned lead', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $ownLead = Lead::factory()->create(['assigned_to_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/call-logs', [
        'lead_id' => $ownLead->id,
        'called_at' => '2026-05-06 12:28:43',
        'outcome' => 'No Answer',
    ])->assertCreated();
});
