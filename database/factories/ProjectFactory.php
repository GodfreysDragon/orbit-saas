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
     * Realistic sample projects, as [name, description] pairs.
     *
     * @var list<array{string, string}>
     */
    public const SAMPLES = [
        ['Customer Portal', 'Self-service portal for invoices, support tickets and account settings.'],
        ['Marketing Site Redesign', 'Refresh the public site with the new brand and faster page loads.'],
        ['Public API v2', 'Versioned REST API with token auth, rate limits and OpenAPI docs.'],
        ['Analytics Dashboard', 'Usage and revenue charts for the leadership weekly review.'],
        ['Postgres Migration', 'Move production from MySQL to Postgres with zero downtime.'],
        ['Mobile App Launch', 'Ship the iOS and Android apps to the stores for the spring release.'],
        ['Onboarding Flow', 'Guided setup checklist that gets new teams to first value faster.'],
        ['Billing Revamp', 'Annual plans, usage-based add-ons and a clearer invoices page.'],
        ['Search Indexing', 'Full-text search across projects and comments with typo tolerance.'],
        ['SSO Integration', 'SAML and Google Workspace sign-in for enterprise customers.'],
        ['Help Center', 'Searchable knowledge base with articles linked from the app.'],
        ['Data Export', 'Scheduled CSV and JSON exports delivered to S3 or email.'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $description] = fake()->randomElement(self::SAMPLES);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'description' => $description,
            'status' => fake()->randomElement([ProjectStatus::Active, ProjectStatus::Active, ProjectStatus::Paused, ProjectStatus::Archived]),
        ];
    }
}
