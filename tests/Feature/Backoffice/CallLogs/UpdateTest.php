<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\User;

it('renders the edit form for an admin', function () {
    $admin = User::factory()->admin()->create();
    $callLog = CallLog::factory()->create();

    $this->actingAs($admin)
        ->get("/backoffice/call-logs/{$callLog->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/call-logs/edit')
            ->where('callLog.id', $callLog->id)
            ->has('outcomes'));
});

it('updates outcome and notes', function () {
    $admin = User::factory()->admin()->create();
    $callLog = CallLog::factory()->create([
        'outcome' => 'No Answer',
        'notes' => 'Original note.',
    ]);

    $response = $this->actingAs($admin)
        ->put("/backoffice/call-logs/{$callLog->id}", [
            'outcome' => 'Successful Contact',
            'notes' => 'Reached the lead, scheduled a callback.',
        ]);

    $response
        ->assertRedirect("/backoffice/call-logs/{$callLog->id}")
        ->assertSessionHas('success');

    expect($callLog->fresh())
        ->outcome->toBe('Successful Contact')
        ->notes->toBe('Reached the lead, scheduled a callback.');
});

it('does not modify lead, user, called_at, or duration on update', function () {
    $admin = User::factory()->admin()->create();
    $callLog = CallLog::factory()->create([
        'duration' => 120,
        'outcome' => 'Busy',
    ]);
    $originalLeadId = $callLog->lead_id;
    $originalUserId = $callLog->user_id;
    $originalCalledAt = $callLog->called_at->toDateTimeString();
    $originalDuration = $callLog->duration;

    $this->actingAs($admin)->put("/backoffice/call-logs/{$callLog->id}", [
        'outcome' => 'Follow-up',
        'notes' => 'Updated.',
        // Attempted overrides — should be ignored.
        'lead_id' => 99999,
        'user_id' => 99999,
        'called_at' => '2099-01-01 00:00:00',
        'duration' => 9999,
    ]);

    $fresh = $callLog->fresh();

    expect($fresh->lead_id)->toBe($originalLeadId)
        ->and($fresh->user_id)->toBe($originalUserId)
        ->and($fresh->called_at->toDateTimeString())->toBe($originalCalledAt)
        ->and($fresh->duration)->toBe($originalDuration);
});

it('rejects an outcome not in the configured list', function () {
    $admin = User::factory()->admin()->create();
    $callLog = CallLog::factory()->create();

    $this->actingAs($admin)
        ->put("/backoffice/call-logs/{$callLog->id}", [
            'outcome' => 'Some Made-Up Outcome',
        ])
        ->assertSessionHasErrors('outcome');
});

it('accepts a null outcome (clearing the field)', function () {
    $admin = User::factory()->admin()->create();
    $callLog = CallLog::factory()->create(['outcome' => 'Busy']);

    $this->actingAs($admin)
        ->put("/backoffice/call-logs/{$callLog->id}", [
            'outcome' => null,
            'notes' => $callLog->notes,
        ])
        ->assertRedirect();

    expect($callLog->fresh()->outcome)->toBeNull();
});

it('rejects an agent attempting to update a call log', function () {
    $agent = User::factory()->agent()->create();
    $callLog = CallLog::factory()->create();

    $this->actingAs($agent)
        ->put("/backoffice/call-logs/{$callLog->id}", [
            'outcome' => 'Follow-up',
        ])
        ->assertForbidden();
});
