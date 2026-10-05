<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

class ProjectObserver
{
    public function creating(Project $project): void
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $project->created_by_id ??= $user->id;
        }
    }

    public function created(Project $project): void
    {
        Activity::record('project.created', "Created project \"{$project->name}\"", $project, organization: $project->organization);
    }

    public function updated(Project $project): void
    {
        $changes = array_keys($project->getChanges());
        $changes = array_values(array_diff($changes, ['updated_at']));

        if ($changes === []) {
            return;
        }

        Activity::record('project.updated', "Updated project \"{$project->name}\"", $project, [
            'changed' => $changes,
        ], $project->organization);
    }

    public function deleted(Project $project): void
    {
        Activity::record('project.deleted', "Deleted project \"{$project->name}\"", properties: [
            'project_id' => $project->getKey(),
        ], organization: $project->organization);
    }
}
