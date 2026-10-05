<?php

namespace Database\Factories;

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'personal' => false,
            'trial_ends_at' => null,
        ];
    }

    /**
     * The owner is always a member holding the Owner role.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            app(AddOrganizationMember::class)->handle($organization, $organization->owner, OrganizationRole::Owner);

            if ($organization->owner->current_organization_id === null) {
                $organization->owner->switchOrganization($organization);
            }
        });
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => ['personal' => true]);
    }

    public function onTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => ['trial_ends_at' => now()->addDays($days)]);
    }

    /**
     * Add a member with the given role after the organization is created.
     */
    public function withMember(User $user, OrganizationRole $role = OrganizationRole::Member): static
    {
        return $this->afterCreating(function (Organization $organization) use ($user, $role): void {
            app(AddOrganizationMember::class)->handle($organization, $user, $role);
        });
    }
}
