<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Send the user to the organization they last worked in. If it is gone,
     * or they are no longer a member, fall back to another membership
     * (preferring the personal one), or to creating an organization.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $organization = $user->currentOrganization;

        if ($organization === null || ! $user->belongsToOrganization($organization)) {
            $organization = $user->switchToFallbackOrganization();
        }

        // Keep toasts flashed by the request that redirected here.
        $request->session()->reflash();

        if ($organization === null) {
            return to_route('organizations.create');
        }

        return to_route('organization.dashboard', $organization);
    }
}
