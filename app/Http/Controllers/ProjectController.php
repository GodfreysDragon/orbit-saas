<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Project;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): Response
    {
        Gate::authorize('viewAny', Project::class);

        $organization = $current->getOrFail();
        $search = $request->string('search')->trim()->toString();
        $status = ProjectStatus::tryFrom($request->string('status')->toString());

        $projects = Project::with('creator:id,name')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status->value,
                'creator' => $project->creator?->name,
                'createdAt' => $project->created_at?->toIso8601String(),
                'updatedAt' => $project->updated_at?->toIso8601String(),
            ]);

        $limit = $organization->plan()->maxProjects;
        $createResponse = Gate::inspect('create', Project::class);

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'filters' => ['search' => $search, 'status' => $status?->value],
            'statuses' => collect(ProjectStatus::cases())->map(fn (ProjectStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'usage' => ['used' => Project::count(), 'limit' => $limit],
            'can' => [
                'create' => $createResponse->allowed(),
                'createReason' => $createResponse->message(),
                'update' => $request->user()->hasPermissionIn($organization, Permission::UpdateProjects),
                'delete' => $request->user()->hasPermissionIn($organization, Permission::DeleteProjects),
            ],
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        Project::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return back();
    }
}
