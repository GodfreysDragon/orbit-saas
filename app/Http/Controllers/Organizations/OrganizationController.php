<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\DeleteOrganization;
use App\Actions\Organizations\TransferOwnership;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Models\Activity;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('organizations/Create');
    }

    public function store(OrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $createOrganization->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('dashboard');
    }

    public function edit(Request $request, CurrentOrganization $current): Response
    {
        $organization = $current->getOrFail();

        $canTransfer = Gate::allows('transferOwnership', $organization);

        return Inertia::render('organizations/Settings', [
            'organization' => $organization->only('id', 'name', 'slug', 'personal', 'created_at'),
            'owner' => $organization->owner->only('id', 'name', 'email'),
            // Admins are the only members ownership can go to.
            'admins' => $canTransfer
                ? $organization->users()->orderBy('name')->get()
                    ->filter(fn (User $member) => $member->roleIn($organization) === OrganizationRole::Admin)
                    ->map->only('id', 'name', 'email')
                    ->values()
                : [],
            'can' => [
                'update' => Gate::allows('update', $organization),
                'delete' => Gate::inspect('delete', $organization)->allowed(),
                'deleteReason' => Gate::inspect('delete', $organization)->message(),
                'transfer' => $canTransfer,
            ],
        ]);
    }

    public function transfer(Request $request, CurrentOrganization $current, TransferOwnership $transferOwnership): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('transferOwnership', $organization);

        $validated = $request->validate([
            'member' => ['required', 'integer'],
            'password' => ['required', 'current_password'],
        ]);

        $member = $organization->users()->whereKey($validated['member'])->first();

        if ($member === null) {
            throw ValidationException::withMessages(['member' => __('Choose a member of this organization.')]);
        }

        Gate::authorize('transferOwnershipTo', [$organization, $member]);

        $transferOwnership->handle($organization, $member, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now the owner of :organization.', [
            'name' => $member->name,
            'organization' => $organization->name,
        ])]);

        return to_route('organization.edit');
    }

    public function update(OrganizationRequest $request, CurrentOrganization $current): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('update', $organization);

        $organization->update($request->validated());

        if ($organization->wasChanged('name')) {
            Activity::record('organization.updated', "Renamed the organization to \"{$organization->name}\"", $organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organization.edit');
    }

    public function destroy(Request $request, CurrentOrganization $current, DeleteOrganization $deleteOrganization): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $organization = $current->getOrFail();

        Gate::authorize('delete', $organization);

        $deleteOrganization->handle($organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('dashboard');
    }
}
