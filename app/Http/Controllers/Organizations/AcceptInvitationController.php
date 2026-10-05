<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);

        return Inertia::render('invitations/Show', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'role' => $invitation->role->toArray(),
                'organization' => $invitation->organization->only('name'),
                'invitedBy' => $invitation->inviter?->name,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
                'expired' => $invitation->isExpired(),
            ],
            'emailMatches' => $this->emailMatches($request, $invitation),
            'alreadyMember' => $invitation->organization->hasUser($request->user()),
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        abort_if($invitation->isExpired(), 410, 'This invitation has expired.');
        abort_unless($this->emailMatches($request, $invitation), 403, 'This invitation was sent to a different email address.');

        $organization = $acceptInvitation->handle($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :name!', ['name' => $organization->name])]);

        return to_route('dashboard');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        abort_unless($this->emailMatches($request, $invitation), 403);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }

    private function findInvitation(string $token): OrganizationInvitation
    {
        return OrganizationInvitation::with('organization', 'inviter')
            ->where('token', $token)
            ->firstOrFail();
    }

    /**
     * Invitations can only be accepted by the (verified) address they were sent to.
     */
    private function emailMatches(Request $request, OrganizationInvitation $invitation): bool
    {
        return Str::lower($request->user()->email) === Str::lower($invitation->email);
    }
}
