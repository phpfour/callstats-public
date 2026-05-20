<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the edit form for an admin', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)
        ->get("/backoffice/leads/{$lead->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/leads/edit')
            ->where('lead.id', $lead->id));
});

it('updates an existing lead', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create([
        'name' => 'Old Name',
        'study_destination' => 'UK',
    ]);

    $response = $this->actingAs($admin)->put("/backoffice/leads/{$lead->id}", [
        'name' => 'New Name',
        'phone_number' => $lead->phone_number,
        'study_destination' => 'Canada',
    ]);

    $response->assertRedirect('/backoffice/leads')->assertSessionHas('success');

    expect($lead->fresh())
        ->name->toBe('New Name')
        ->study_destination->toBe('Canada');
});

it('stamps assigned_at when assigning a lead via update', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();
    $lead = Lead::factory()->create([
        'assigned_to_id' => null,
        'assigned_at' => null,
    ]);

    Carbon::setTestNow('2026-06-01 14:30:00');

    $this->actingAs($admin)->put("/backoffice/leads/{$lead->id}", [
        'name' => $lead->name,
        'phone_number' => $lead->phone_number,
        'assigned_to_id' => $assignee->id,
    ]);

    expect($lead->fresh()->assigned_at?->toDateTimeString())->toBe('2026-06-01 14:30:00');
});

it('allows the same phone_number on the lead being edited (unique-ignore)', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['phone_number' => '+8801555000000']);

    $this->actingAs($admin)
        ->put("/backoffice/leads/{$lead->id}", [
            'name' => $lead->name,
            'phone_number' => $lead->phone_number,
        ])
        ->assertRedirect('/backoffice/leads');
});

it('rejects an agent attempting to update a lead', function () {
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($agent)
        ->put("/backoffice/leads/{$lead->id}", [
            'name' => 'Hijack',
            'phone_number' => $lead->phone_number,
        ])
        ->assertForbidden();
});
