<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentScorecard;
use App\Models\AgentScorecardOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentScorecardOutcome>
 */
class AgentScorecardOutcomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agent_scorecard_id' => AgentScorecard::factory(),
            'outcome' => fake()->randomElement([
                'Successful Contact', 'Interested', 'Follow-up', 'No Answer', 'Not Interested', 'Busy',
            ]),
            'count' => fake()->numberBetween(1, 20),
        ];
    }
}
