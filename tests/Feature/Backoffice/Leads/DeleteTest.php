<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;

it('deletes a lead and redirects with a success flash', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)
        ->delete("/backoffice/leads/{$lead->id}")
        ->assertRedirect('/backoffice/leads')
        ->assertSessionHas('success');

    expect(Lead::find($lead->id))->toBeNull();
});

it('cascades the delete to related call_logs and reminders', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();
    $callLog = CallLog::factory()->create(['lead_id' => $lead->id]);
    $reminder = Reminder::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($admin)
        ->delete("/backoffice/leads/{$lead->id}")
        ->assertRedirect('/backoffice/leads');

    expect(CallLog::find($callLog->id))->toBeNull()
        ->and(Reminder::find($reminder->id))->toBeNull();
});

it('rejects an agent attempting to delete a lead', function () {
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($agent)
        ->delete("/backoffice/leads/{$lead->id}")
        ->assertForbidden();

    expect(Lead::find($lead->id))->not->toBeNull();
});

it('returns 404 when deleting a missing lead', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete('/backoffice/leads/999999')
        ->assertNotFound();
});
