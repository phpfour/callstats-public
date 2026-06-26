<?php

declare(strict_types=1);

use App\Models\AgentScorecard;
use App\Models\User;
use Illuminate\Support\Carbon;

it('renders the leaderboard for an admin', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['name' => 'Board Agent']);

    AgentScorecard::factory()
        ->forAgent($agent)
        ->withOutcomes(['Interested' => 2])
        ->create([
            'scorecard_date' => Carbon::today()->toDateString(),
            'total_calls' => 20,
            'conversions' => 8,
        ]);

    $this->actingAs($admin)
        ->get('/backoffice/leaderboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/leaderboard')
            ->has('rows', 1, fn ($row) => $row
                ->where('name', 'Board Agent')
                ->where('conversions', 8)
                ->etc())
            ->where('featured.name', 'Board Agent')
            ->has('generatedAt'));
});

it('renders an empty featured slot when there are no scorecards', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backoffice/leaderboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/leaderboard')
            ->where('featured', null));
});

it('forbids agents from the leaderboard', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->get('/backoffice/leaderboard')
        ->assertForbidden();
});
