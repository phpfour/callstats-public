<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'called_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'duration' => fake()->numberBetween(0, 1800),
            'notes' => fake()->optional()->sentence(),
            'outcome' => fake()->randomElement([
                'Successful Contact',
                'No Answer',
                'Missed',
                'Follow-up',
                'Not Interested',
            ]),
        ];
    }
}
