<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the `{organization}` slug in the URL.
 *
 * Users who don't belong to the organization get a 404, which doesn't reveal
 * whether it exists. The organization is remembered as the user's current
 * one, which is where the `dashboard` entry point sends them next time.
 */
class EnsureOrganizationContext
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $slug = $request->route('organization');

        $organization = is_string($slug)
            ? Organization::where('slug', $slug)->first()
            : null;

        abort_if($organization === null || ! $user->belongsToOrganization($organization), 404);

        if ($user->current_organization_id !== $organization->getKey()) {
            $user->switchOrganization($organization);
        }

        $this->current->set($organization);

        // Roles loaded earlier belong to another team (a long-lived user
        // instance, e.g. under Octane), so reload them for this tenant.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // URLs generated during this request stay in this organization, and
        // controllers don't receive the slug as an argument.
        URL::defaults(['organization' => $organization->slug]);
        $request->route()?->forgetParameter('organization');

        return $next($request);
    }
}
