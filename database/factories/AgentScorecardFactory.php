<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentScorecard;
use App\Models\AgentScorecardOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentScorecard>
 */
class AgentScorecardFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');

        $totalCalls = fake()->numberBetween(5, 40);
        $connected = fake()->numberBetween(0, $totalCalls);
        $conversions = fake()->numberBetween(0, $connected);
        $flagged = fake()->boolean(15);

        return [
            'user_id' => User::factory()->agent(),
            'scorecard_date' => $date->format('Y-m-d'),
            'status' => $flagged ? 'flagged' : 'final',
            'total_calls' => $totalCalls,
            'connected_calls' => $connected,
            'conversions' => $conversions,
            'talk_time_seconds' => fake()->numberBetween(0, $totalCalls * 300),
            'conversion_rate' => $totalCalls > 0 ? round($conversions / $totalCalls * 100, 1) : 0.0,
            'review' => $flagged,
            'raw_payload' => [
                'review' => $flagged,
                'source' => fake()->randomElement(['inbound', 'outbound']),
            ],
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    /**
     * Tie this scorecard to an existing agent user.
     */
    public function forAgent(User $agent): static
    {
        return $this->state(fn (): array => [
            'user_id' => $agent->id,
        ]);
    }

    /**
     * Attach a set of top outcomes to the scorecard.
     *
     * @param  array<string, int>  $outcomes  outcome name => count
     */
    public function withOutcomes(array $outcomes): static
    {
        return $this->afterCreating(function (AgentScorecard $scorecard) use ($outcomes): void {
            foreach ($outcomes as $outcome => $count) {
                AgentScorecardOutcome::create([
                    'agent_scorecard_id' => $scorecard->id,
                    'outcome' => $outcome,
                    'count' => $count,
                ]);
            }
        });
    }
}
