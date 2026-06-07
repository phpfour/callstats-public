<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('includes calls at both edges of the called_from/called_to range', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();

    // Boundary rows: first instant of the "from" day and last instant of the "to" day.
    $startEdge = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-06-01 00:00:00'),
    ]);
    $endEdge = CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-06-03 23:59:59'),
    ]);
    // Just outside the window on each side.
    CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-05-31 23:59:59'),
    ]);
    CallLog::factory()->create([
        'lead_id' => $lead->id,
        'called_at' => Carbon::parse('2026-06-04 00:00:00'),
    ]);

    $this->actingAs($admin)
        ->get('/backoffice/call-logs?called_from=2026-06-01&called_to=2026-06-03')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('callLogs.data', 2));

    expect([$startEdge->id, $endEdge->id])->toHaveCount(2);
});

it("counts a call at 23:59 today in the agent's today stats", function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $lead = Lead::factory()->create();

    CallLog::factory()->create([
        'user_id' => $agent->id,
        'lead_id' => $lead->id,
        'called_at' => Carbon::today()->setTime(23, 59, 0),
        'outcome' => 'Interested',
    ]);
    // Yesterday's late call must not leak into "today".
    CallLog::factory()->create([
        'user_id' => $agent->id,
        'lead_id' => $lead->id,
        'called_at' => Carbon::yesterday()->setTime(23, 59, 0),
        'outcome' => 'Interested',
    ]);

    $this->actingAs($admin)
        ->get("/backoffice/agents/{$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.today.calls', 1)
            ->where('agent.today.conversions', 1));
});
