<?php

namespace App\Actions\Fortify;

use App\Actions\Organizations\AcceptInvitation;
use App\Actions\Organizations\CreateOrganization;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private CreateOrganization $createOrganization,
        private AcceptInvitation $acceptInvitation,
    ) {}

    /**
     * Validate and create a newly registered user along with their
     * personal organization. Signing up from an invitation link accepts it.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['nullable', 'string'],
        ])->validate();

        $invitation = OrganizationInvitation::findPending($input['invitation'] ?? null);

        return DB::transaction(function () use ($input, $invitation): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->createOrganization->handle(
                $user,
                Str::of($input['name'])->before(' ')->append("'s Workspace")->toString(),
                personal: true,
            );

            // The demo sends no real email, so sign-ups couldn't verify.
            if (config('app.demo')) {
                $user->markEmailAsVerified();
            }

            if ($invitation?->isFor($user->email)) {
                // The invitation link was delivered to this address, so it is
                // already verified. Marking it now (before Fortify fires the
                // Registered event) also skips the verification email.
                $user->markEmailAsVerified();

                $organization = $this->acceptInvitation->handle($invitation, $user);

                // Fortify redirects to the intended URL, which is the now
                // consumed invitation page. Land on the new dashboard instead.
                session()->forget('url.intended');
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :name!', ['name' => $organization->name])]);
            }

            return $user;
        });
    }
}
