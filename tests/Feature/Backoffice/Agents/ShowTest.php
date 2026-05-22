<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the agent detail page for an admin', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->withKpiTargets(30, 50)->create([
        'name' => 'Alex Agent',
        'code' => 'A-001',
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/agents/show')
            ->where('agent.id', $agent->id)
            ->where('agent.name', 'Alex Agent')
            ->where('agent.code', 'A-001')
            ->where('agent.targets.dailyCalls', 30)
            ->where('agent.targets.conversionRate', 50));
});

it('allows supervisors to view the agent detail page', function () {
    $supervisor = User::factory()->supervisor()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($supervisor)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk();
});

it('forbids agents from the agent detail page', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertForbidden();
});

it('404s when the target user is not an agent', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$supervisor->id}")
        ->assertNotFound();
});

it("counts today's calls and computes today's conversion rate", function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    $today = Carbon::today()->setTime(10, 0);

    foreach (['Successful Contact', 'Follow-up', 'Interested', 'Call Back Requested'] as $outcome) {
        CallLog::factory()->create([
            'user_id' => $agent->id,
            'lead_id' => $lead->id,
            'called_at' => $today,
            'outcome' => $outcome,
        ]);
    }

    foreach (['Missed', 'No Answer', 'Not Interested', 'Missed', 'No Answer', 'Not Interested'] as $outcome) {
        CallLog::factory()->create([
            'user_id' => $agent->id,
            'lead_id' => $lead->id,
            'called_at' => $today,
            'outcome' => $outcome,
        ]);
    }

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.today.calls', 10)
            ->where('agent.today.conversions', 4)
            ->where('agent.today.conversionRate', 40));
});

it('returns null today conversion rate when there are no calls today', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.today.calls', 0)
            ->where('agent.today.conversionRate', null));
});

it('aggregates the last 30 days of calls', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    CallLog::factory()->create([
        'user_id' => $agent->id,
        'lead_id' => $lead->id,
        'called_at' => Carbon::today()->subDays(5)->setTime(11, 0),
        'duration' => 60,
        'outcome' => 'Successful Contact',
    ]);

    CallLog::factory()->create([
        'user_id' => $agent->id,
        'lead_id' => $lead->id,
        'called_at' => Carbon::today()->subDays(20)->setTime(9, 0),
        'duration' => 120,
        'outcome' => 'Missed',
    ]);

    // Outside the 30-day window — must not be counted.
    CallLog::factory()->create([
        'user_id' => $agent->id,
        'lead_id' => $lead->id,
        'called_at' => Carbon::today()->subDays(40),
        'duration' => 9999,
        'outcome' => 'Successful Contact',
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.last30Days.calls', 2)
            ->where('agent.last30Days.talkTime', 180)
            ->where('agent.last30Days.conversions', 1)
            ->where('agent.last30Days.conversionRate', 50)
            ->where('agent.last30Days.avgDuration', 90));
});

it('returns at most 25 recent calls, ordered newest first', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        CallLog::factory()->create([
            'user_id' => $agent->id,
            'lead_id' => $lead->id,
            'called_at' => Carbon::now()->subMinutes($i),
        ]);
    }

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('agent.recentCalls', 25));
});

it('exposes nulls in the targets payload when no KPI row exists', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.targets.dailyCalls', null)
            ->where('agent.targets.conversionRate', null));
});

it('exposes individual null targets when only one is set', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->withKpiTargets(20, null)->create();

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.targets.dailyCalls', 20)
            ->where('agent.targets.conversionRate', null));
});
