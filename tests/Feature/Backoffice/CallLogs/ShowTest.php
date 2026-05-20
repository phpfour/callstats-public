<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;

it('renders the call-log show page with eager-loaded relations', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['name' => 'Aisha Khan']);
    $agent = User::factory()->agent()->create(['name' => 'Bob Builder']);
    $callLog = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'outcome' => 'Successful Contact',
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/call-logs/{$callLog->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/call-logs/show')
            ->where('callLog.id', $callLog->id)
            ->where('callLog.outcome', 'Successful Contact')
            ->where('callLog.lead.name', 'Aisha Khan')
            ->where('callLog.user.name', 'Bob Builder'));
});

it('rejects an agent attempting to view a call log', function () {
    $agent = User::factory()->agent()->create();
    $callLog = CallLog::factory()->create();

    $this->actingAs($agent)
        ->get("/backoffice/call-logs/{$callLog->id}")
        ->assertForbidden();
});

it('returns 404 for a missing call log', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/call-logs/999999')
        ->assertNotFound();
});
