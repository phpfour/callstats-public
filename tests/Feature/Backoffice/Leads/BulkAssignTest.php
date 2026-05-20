<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('reassigns the selected leads and redirects with a success flash', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();
    $leads = Lead::factory()->count(3)->create(['assigned_to_id' => null]);

    $this->actingAs($admin)
        ->post('/backoffice/leads/bulk-assign', [
            'lead_ids' => $leads->pluck('id')->all(),
            'assigned_to_id' => $assignee->id,
        ])
        ->assertRedirect('/backoffice/leads')
        ->assertSessionHas('success', 'Assigned 3 leads.');

    foreach ($leads as $lead) {
        expect($lead->fresh()->assigned_to_id)->toBe($assignee->id);
    }
});

it('stamps assigned_at on every lead via the model saving hook', function () {
    Carbon::setTestNow('2026-07-04 09:00:00');

    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();
    $leads = Lead::factory()->count(2)->create([
        'assigned_to_id' => null,
        'assigned_at' => null,
    ]);

    $this->actingAs($admin)->post('/backoffice/leads/bulk-assign', [
        'lead_ids' => $leads->pluck('id')->all(),
        'assigned_to_id' => $assignee->id,
    ]);

    foreach ($leads as $lead) {
        expect($lead->fresh()->assigned_at?->toDateTimeString())->toBe('2026-07-04 09:00:00');
    }
});

it('requires lead_ids and assigned_to_id', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/backoffice/leads/bulk-assign', [])
        ->assertSessionHasErrors(['lead_ids', 'assigned_to_id']);
});

it('rejects unknown lead ids', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->post('/backoffice/leads/bulk-assign', [
            'lead_ids' => [999999],
            'assigned_to_id' => $assignee->id,
        ])
        ->assertSessionHasErrors('lead_ids.0');
});

it('rejects an agent attempting to bulk-assign', function () {
    $agent = User::factory()->agent()->create();
    $assignee = User::factory()->agent()->create();
    $lead = Lead::factory()->create(['assigned_to_id' => null]);

    $this->actingAs($agent)
        ->post('/backoffice/leads/bulk-assign', [
            'lead_ids' => [$lead->id],
            'assigned_to_id' => $assignee->id,
        ])
        ->assertForbidden();

    expect($lead->fresh()->assigned_to_id)->toBeNull();
});
