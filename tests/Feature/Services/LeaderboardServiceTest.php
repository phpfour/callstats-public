<?php

declare(strict_types=1);

use App\Models\AgentScorecard;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, int>  $outcomes
 */
function scorecard(User $agent, string $date, array $attributes = [], array $outcomes = []): AgentScorecard
{
    return AgentScorecard::factory()
        ->forAgent($agent)
        ->withOutcomes($outcomes)
        ->create(array_merge(['scorecard_date' => $date], $attributes));
}

function service(): LeaderboardService
{
    return app(LeaderboardService::class);
}

it('ranks agents by total conversions, biggest first', function () {
    $low = User::factory()->agent()->create(['name' => 'Low Performer']);
    $high = User::factory()->agent()->create(['name' => 'High Performer']);

    scorecard($low, '2026-06-01', ['total_calls' => 10, 'conversions' => 2]);
    scorecard($high, '2026-06-01', ['total_calls' => 10, 'conversions' => 9]);

    $rows = service()->build('2026-06-01', '2026-06-02');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('High Performer')
        ->and($rows[0]['conversions'])->toBe(9)
        ->and($rows[1]['name'])->toBe('Low Performer');
});

it('sums only scorecards inside the date window', function () {
    $agent = User::factory()->agent()->create();

    scorecard($agent, '2026-06-01', ['total_calls' => 10, 'conversions' => 5]);
    scorecard($agent, '2026-06-15', ['total_calls' => 10, 'conversions' => 3]);
    scorecard($agent, '2026-07-01', ['total_calls' => 10, 'conversions' => 99]); // out of range

    $rows = service()->build('2026-06-01', '2026-06-30');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['conversions'])->toBe(8)
        ->and($rows[0]['days'])->toBe(2);
});

it('counts flagged_days from the agent own rows only (regression)', function () {
    $agent = User::factory()->agent()->create(['name' => 'Subject']);
    $other = User::factory()->agent()->create(['name' => 'Other']);

    // Subject has exactly one flagged day.
    scorecard($agent, '2026-06-01', ['status' => 'flagged', 'review' => true, 'conversions' => 0]);
    scorecard($agent, '2026-06-02', ['status' => 'final', 'review' => false, 'conversions' => 4]);

    // Another agent has review=true rows that must NOT bleed into Subject's count.
    scorecard($other, '2026-06-01', ['status' => 'flagged', 'review' => true, 'conversions' => 0]);
    scorecard($other, '2026-06-02', ['status' => 'final', 'review' => true, 'conversions' => 0]);

    $rows = collect(service()->build('2026-06-01', '2026-06-30'))->keyBy('name');

    expect($rows['Subject']['flagged_days'])->toBe(1)
        ->and($rows['Other']['flagged_days'])->toBe(2);
});

it('derives interested_days from the outcomes child table', function () {
    $agent = User::factory()->agent()->create();

    scorecard($agent, '2026-06-01', [], ['Interested' => 3, 'Follow-up' => 1]);
    scorecard($agent, '2026-06-02', [], ['No Answer' => 5]);
    scorecard($agent, '2026-06-03', [], ['Interested' => 1]);

    $rows = service()->build('2026-06-01', '2026-06-30');

    expect($rows[0]['interested_days'])->toBe(2);
});

it('computes the conversion rate from summed calls and conversions', function () {
    $agent = User::factory()->agent()->create();

    scorecard($agent, '2026-06-01', ['total_calls' => 30, 'conversions' => 6]);
    scorecard($agent, '2026-06-02', ['total_calls' => 10, 'conversions' => 4]);

    $rows = service()->build('2026-06-01', '2026-06-30');

    // 10 conversions / 40 calls = 25.0%
    expect($rows[0]['conversion_rate'])->toBe(25.0);
});

it('returns a zeroed row for an agent with no scorecards', function () {
    $agent = User::factory()->agent()->create(['name' => 'Idle']);

    $rows = service()->build('2026-06-01', '2026-06-30');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Idle')
        ->and($rows[0]['days'])->toBe(0)
        ->and($rows[0]['conversions'])->toBe(0)
        ->and($rows[0]['conversion_rate'])->toBe(0.0);
});

it('uses the same number of queries regardless of agent count (no N+1)', function () {
    $seed = function (int $count): void {
        User::factory()->agent()->count($count)->create()->each(
            fn (User $agent, int $i) => scorecard($agent, '2026-06-01', ['conversions' => $i], ['Interested' => 1]),
        );
    };

    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        service()->build('2026-06-01', '2026-06-30');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $seed(3);
    $few = $measure();

    $seed(9); // many more agents...
    $many = $measure();

    // Query count is constant — it does not grow with the number of agents.
    expect($many)->toBe($few);
});

it('returns null featured agent when there are no scorecards', function () {
    expect(service()->featuredAgent())->toBeNull();
});

it('features an existing agent by name when scorecards exist', function () {
    $agent = User::factory()->agent()->create(['name' => 'Featured One']);
    scorecard($agent, Carbon::today()->toDateString(), ['conversions' => 7]);

    $featured = service()->featuredAgent();

    expect($featured)->not->toBeNull()
        ->and($featured['name'])->toBe('Featured One')
        ->and($featured['conversions'])->toBe(7);
});
