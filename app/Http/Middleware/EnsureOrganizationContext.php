<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the request from the user's current organization.
 *
 * If the stored organization is missing or the user is no longer a member,
 * the user falls back to another organization they belong to, or is sent to
 * create one.
 */
class EnsureOrganizationContext
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $organization = $user->currentOrganization;

        if ($organization === null || ! $user->belongsToOrganization($organization)) {
            $organization = $user->switchToFallbackOrganization();
        }

        if ($organization === null) {
            return to_route('organizations.create');
        }

        $this->current->set($organization);

        return $next($request);
    }
}
