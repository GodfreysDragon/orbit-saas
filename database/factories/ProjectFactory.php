<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst(fake()->word()).' '.fake()->randomElement(['Portal', 'Redesign', 'API', 'Dashboard', 'Migration', 'Launch', 'Mobile App', 'Analytics']),
            'description' => fake()->sentence(12),
            'status' => fake()->randomElement([ProjectStatus::Active, ProjectStatus::Active, ProjectStatus::Paused, ProjectStatus::Archived]),
        ];
    }
}
