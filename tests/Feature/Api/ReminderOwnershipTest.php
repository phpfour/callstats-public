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

it('rejects a reminder against a lead assigned to another agent', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $otherAgent = User::factory()->create();
    $otherAgent->assignRole(UserRole::AGENT->value);

    $foreignLead = Lead::factory()->create(['assigned_to_id' => $otherAgent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/reminders', [
        'lead_id' => $foreignLead->id,
        'remind_at' => '2026-06-01 09:00:00',
    ])->assertStatus(422)->assertJsonValidationErrors(['lead_id']);

    expect(Reminder::count())->toBe(0);
});

it('rejects a reminder linked to another agents call log', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $otherAgent = User::factory()->create();
    $otherAgent->assignRole(UserRole::AGENT->value);

    $ownLead = Lead::factory()->create(['assigned_to_id' => $agent->id]);
    $foreignCallLog = CallLog::factory()->create(['user_id' => $otherAgent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/reminders', [
        'lead_id' => $ownLead->id,
        'call_log_id' => $foreignCallLog->id,
        'remind_at' => '2026-06-01 09:00:00',
    ])->assertStatus(422)->assertJsonValidationErrors(['call_log_id']);
});

it('allows a reminder on the agents own lead and own call log', function () {
    $agent = User::factory()->create();
    $agent->assignRole(UserRole::AGENT->value);

    $ownLead = Lead::factory()->create(['assigned_to_id' => $agent->id]);
    $ownCallLog = CallLog::factory()->create(['lead_id' => $ownLead->id, 'user_id' => $agent->id]);

    Sanctum::actingAs($agent);

    $this->postJson('/api/reminders', [
        'lead_id' => $ownLead->id,
        'call_log_id' => $ownCallLog->id,
        'remind_at' => '2026-06-01 09:00:00',
    ])->assertCreated();
});
