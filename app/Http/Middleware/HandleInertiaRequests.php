<?php

namespace App\Http\Middleware;

use App\Billing\SubscriptionSummary;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Organization props are closures because they are resolved after the
     * route's `organization` middleware has set the current tenant.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'organization' => fn () => $this->currentOrganization($request),
                'organizations' => fn () => $request->user()?->organizations()
                    ->orderBy('name')
                    ->get(['organizations.id', 'name', 'slug', 'personal'])
                    ->map->only('id', 'name', 'slug', 'personal'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'pageGuides' => config('app.page_guides'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentOrganization(Request $request): ?array
    {
        $organization = app(CurrentOrganization::class)->get();

        /** @var User|null $user */
        $user = $request->user();

        if ($organization === null || $user === null) {
            return null;
        }

        $billing = SubscriptionSummary::for($organization);

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'personal' => $organization->personal,
            'role' => $user->roleIn($organization)?->toArray(),
            'permissions' => $user->permissionsIn($organization),
            'plan' => ['key' => $billing['plan']['key'], 'name' => $billing['plan']['name']],
            'billingStatus' => $billing['status'],
            'trialEndsAt' => $billing['trialEndsAt'],
        ];
    }
}
