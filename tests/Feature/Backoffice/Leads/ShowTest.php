<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the show page for an admin', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->agent()->create();
    $lead = Lead::factory()->create([
        'assigned_to_id' => $assignee->id,
        'study_destination' => 'Australia',
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/leads/{$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/leads/show')
            ->where('lead.id', $lead->id)
            ->where('lead.study_destination', 'Australia')
            ->where('lead.assigned_to.id', $assignee->id)
            ->has('lead.call_logs')
            ->has('lead.reminders'));
});

it('eager-loads call_logs sorted by called_at desc', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    $earlier = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-01-01 09:00'),
    ]);
    $latest = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-01-05 16:00'),
    ]);
    $middle = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-01-03 12:00'),
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/leads/{$lead->id}")
        ->assertInertia(fn ($page) => $page
            ->where('lead.call_logs.0.id', $latest->id)
            ->where('lead.call_logs.1.id', $middle->id)
            ->where('lead.call_logs.2.id', $earlier->id));
});

it('eager-loads reminders sorted by remind_at desc', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    $earlier = Reminder::factory()->create([
        'lead_id' => $lead->id,
        'remind_at' => Carbon::parse('2026-02-01 09:00'),
    ]);
    $latest = Reminder::factory()->create([
        'lead_id' => $lead->id,
        'remind_at' => Carbon::parse('2026-02-15 16:00'),
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/leads/{$lead->id}")
        ->assertInertia(fn ($page) => $page
            ->where('lead.reminders.0.id', $latest->id)
            ->where('lead.reminders.1.id', $earlier->id));
});

it('renders empty relations sections gracefully', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)
        ->get("/backoffice/leads/{$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lead.call_logs', [])
            ->where('lead.reminders', []));
});

it('rejects an agent attempting to view a lead', function () {
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($agent)
        ->get("/backoffice/leads/{$lead->id}")
        ->assertForbidden();
});

it('returns 404 for a missing lead', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/leads/999999')
        ->assertNotFound();
});
