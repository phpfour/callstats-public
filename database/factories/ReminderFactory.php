<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'call_log_id' => null,
            'user_id' => User::factory(),
            'remind_at' => fake()->dateTimeBetween('now', '+30 days'),
            'notes' => fake()->optional()->sentence(),
            'type' => fake()->randomElement(['callback', 'follow-up']),
        ];
    }

    public function callback(): static
    {
        return $this->state(['type' => 'callback']);
    }
}
