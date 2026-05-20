<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_number' => fake()->unique()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'study_destination' => fake()->randomElement(['UK', 'USA', 'Canada', 'Australia']),
            'source' => fake()->randomElement(['Facebook', 'Instagram', 'Referral', 'Walk-in']),
            'ielts_score' => fake()->randomElement(['6.0', '6.5', '7.0', '7.5', null]),
            'assigned_to_id' => null,
            'assigned_at' => null,
        ];
    }
}
