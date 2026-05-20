<?php

declare(strict_types=1);

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the call-logs index for an admin', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/backoffice/call-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/call-logs/index')
            ->has('callLogs.data', 3)
            ->has('outcomes')
            ->has('agents'));
});

it('rejects an agent attempting to view the call-logs list', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/call-logs')
        ->assertForbidden();
});

it('filters by agent', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create();
    $bob = User::factory()->agent()->create();

    CallLog::factory()->count(2)->create(['user_id' => $alice->id]);
    CallLog::factory()->create(['user_id' => $bob->id]);

    $this->actingAs($admin)
        ->get("/backoffice/call-logs?user_id={$alice->id}")
        ->assertInertia(fn ($page) => $page->has('callLogs.data', 2));
});

it('filters by outcome', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->count(2)->create(['outcome' => 'Successful Contact']);
    CallLog::factory()->create(['outcome' => 'No Answer']);

    $this->actingAs($admin)
        ->get('/backoffice/call-logs?outcome='.urlencode('Successful Contact'))
        ->assertInertia(fn ($page) => $page->has('callLogs.data', 2));
});

it('filters by date range on called_at', function () {
    $admin = User::factory()->admin()->create();

    CallLog::factory()->create(['called_at' => Carbon::parse('2026-01-15 10:00')]);
    CallLog::factory()->create(['called_at' => Carbon::parse('2026-02-15 10:00')]);
    CallLog::factory()->create(['called_at' => Carbon::parse('2026-03-15 10:00')]);

    $this->actingAs($admin)
        ->get('/backoffice/call-logs?called_from=2026-02-01&called_to=2026-02-28')
        ->assertInertia(fn ($page) => $page->has('callLogs.data', 1));
});

it('paginates at 25 per page', function () {
    $admin = User::factory()->admin()->create();
    CallLog::factory()->count(30)->create();

    $this->actingAs($admin)
        ->get('/backoffice/call-logs')
        ->assertInertia(fn ($page) => $page
            ->has('callLogs.data', 25)
            ->where('callLogs.per_page', 25)
            ->where('callLogs.total', 30));
});

it('eager-loads lead and user on each row', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create();
    $agent = User::factory()->agent()->create();
    CallLog::factory()->create(['lead_id' => $lead->id, 'user_id' => $agent->id]);

    $this->actingAs($admin)
        ->get('/backoffice/call-logs')
        ->assertInertia(fn ($page) => $page
            ->where('callLogs.data.0.lead.id', $lead->id)
            ->where('callLogs.data.0.user.id', $agent->id));
});
