<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);
        $user = $request->user();

        $details = [
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role->toArray(),
            'organization' => $invitation->organization->only('name'),
            'invitedBy' => $invitation->inviter?->name,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'expired' => $invitation->isExpired(),
        ];

        if ($user === null) {
            // Come back here after logging in, ready to accept.
            $request->session()->put('url.intended', $request->url());

            return Inertia::render('auth/Invitation', [
                'invitation' => $details,
                'accountExists' => User::where('email', $invitation->email)->exists(),
            ]);
        }

        return Inertia::render('invitations/Show', [
            'invitation' => $details,
            'emailMatches' => $invitation->isFor($user->email),
            'alreadyMember' => $invitation->organization->hasUser($user),
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $user = $request->user();

        abort_if($invitation->isExpired(), 410, 'This invitation has expired.');
        abort_unless($invitation->isFor($user->email), 403, 'This invitation was sent to a different email address.');

        // The token was delivered to this address, which proves the user controls it.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $organization = $acceptInvitation->handle($invitation, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :name!', ['name' => $organization->name])]);

        return to_route('dashboard');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        abort_unless($invitation->isFor($request->user()->email), 403);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }

    private function findInvitation(string $token): OrganizationInvitation
    {
        return OrganizationInvitation::whereToken($token)
            ->with('organization', 'inviter')
            ->firstOrFail();
    }
}
