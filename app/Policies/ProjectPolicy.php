<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function __construct(private CurrentOrganization $current) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionIn($this->current->getOrFail(), Permission::ViewProjects);
    }

    public function create(User $user): Response
    {
        $organization = $this->current->getOrFail();

        if (! $user->hasPermissionIn($organization, Permission::CreateProjects)) {
            return Response::deny();
        }

        return $organization->canCreateProjects()
            ? Response::allow()
            : Response::deny("You've reached the project limit for the {$organization->plan()->name} plan. Upgrade to add more.");
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermissionIn($project->organization, Permission::UpdateProjects);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermissionIn($project->organization, Permission::DeleteProjects);
    }
}
