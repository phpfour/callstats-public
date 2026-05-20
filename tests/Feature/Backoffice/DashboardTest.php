<?php

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard renders the backoffice/dashboard inertia component', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('backoffice/dashboard')
        );
});

test('the dashboard shares the auth user roles with inertia', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['admin'])
        );
});

test('counters reflect the seeded dataset', function () {
    Carbon::setTestNow('2026-05-06 10:00:00');

    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create();
    User::factory()->agent()->create();
    User::factory()->supervisor()->create();

    Lead::factory()->count(3)->create(['assigned_to_id' => $alice->id]);
    Lead::factory()->count(2)->create(['assigned_to_id' => null]);

    CallLog::factory()->create(['called_at' => Carbon::now(), 'duration' => 60]);
    CallLog::factory()->create(['called_at' => Carbon::now(), 'duration' => 120]);
    CallLog::factory()->create(['called_at' => Carbon::yesterday(), 'duration' => 90]);

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counters.totalCalls', 3)
            ->where('counters.availableLeads', 3) // assigned only
            ->where('counters.availableAgents', 2)
            ->where('counters.callsToday', 2)
            ->where('counters.averageDuration', '00:01:30')
        );
});

test('daily chart includes every agent with a zero for inactive ones', function () {
    Carbon::setTestNow('2026-05-06 10:00:00');

    $admin = User::factory()->admin()->create();
    $alice = User::factory()->agent()->create(['name' => 'Alice']);
    User::factory()->agent()->create(['name' => 'Bob']);
    User::factory()->agent()->create(['name' => 'Charlie']);

    CallLog::factory()->count(3)->create([
        'user_id' => $alice->id,
        'called_at' => Carbon::now(),
    ]);

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('charts.daily', 3)
            ->where('charts.daily.0', ['agent' => 'Alice', 'calls' => 3])
            ->where('charts.daily.1', ['agent' => 'Bob', 'calls' => 0])
            ->where('charts.daily.2', ['agent' => 'Charlie', 'calls' => 0])
        );
});

test('weekly chart returns 7 points oldest first with the right labels', function () {
    Carbon::setTestNow('2026-05-06 10:00:00');

    $admin = User::factory()->admin()->create();

    CallLog::factory()->create(['called_at' => Carbon::today()]);
    CallLog::factory()->count(2)->create(['called_at' => Carbon::yesterday()]);
    CallLog::factory()->create(['called_at' => Carbon::today()->subDays(3)]);

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('charts.weekly', 7)
            ->where('charts.weekly.0.label', '6 days ago')
            ->where('charts.weekly.0.calls', 0)
            ->where('charts.weekly.3.label', '3 days ago')
            ->where('charts.weekly.3.calls', 1)
            ->where('charts.weekly.5.label', 'Yesterday')
            ->where('charts.weekly.5.calls', 2)
            ->where('charts.weekly.6.label', 'Today')
            ->where('charts.weekly.6.calls', 1)
        );
});

test('average duration is 00:00:00 when there are no calls', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('backoffice.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counters.averageDuration', '00:00:00')
            ->where('counters.totalCalls', 0)
        );
});
