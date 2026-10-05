<?php

namespace App\Support;

use App\Models\Organization;
use RuntimeException;

/**
 * Holds the tenant for the current request (or job). Registered as a scoped
 * singleton so it is reset between requests under Octane and between queued jobs.
 *
 * Setting the organization also sets Spatie's team id so every role and
 * permission check is evaluated within this organization.
 */
final class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;

        setPermissionsTeamId($organization?->getKey());
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function getOrFail(): Organization
    {
        return $this->organization
            ?? throw new RuntimeException('No organization has been set for the current context.');
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    /**
     * Run the callback with the given organization as the current tenant,
     * restoring the previous tenant afterwards.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;

        $this->set($organization);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }
}
