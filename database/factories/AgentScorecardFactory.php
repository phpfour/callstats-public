<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentScorecard;
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

        $outcomes = fake()->randomElements(
            ['Successful Contact', 'Interested', 'Follow-up', 'No Answer', 'Not Interested', 'Busy'],
            fake()->numberBetween(1, 3),
        );

        $flagged = fake()->boolean(15);

        return [
            'agent_name' => fake()->name(),
            'agent_email' => fake()->safeEmail(),
            'scorecard_date' => $date->format('Y-m-d'),
            'status' => $flagged ? 'flagged' : 'final',
            'total_calls' => $totalCalls,
            'connected_calls' => $connected,
            'conversions' => $conversions,
            'talk_time_seconds' => fake()->numberBetween(0, $totalCalls * 300),
            'conversion_rate' => $totalCalls > 0 ? round($conversions / $totalCalls * 100, 1) : 0.0,
            'top_outcomes' => implode(',', $outcomes),
            'raw_payload' => json_encode([
                'review' => $flagged,
                'source' => fake()->randomElement(['inbound', 'outbound']),
            ]),
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    /**
     * Tie this scorecard to a real agent user so the leaderboard's
     * name-based lookup finds it.
     */
    public function forAgent(User $agent): static
    {
        return $this->state(fn (): array => [
            'agent_name' => $agent->name,
            'agent_email' => $agent->email,
        ]);
    }
}
