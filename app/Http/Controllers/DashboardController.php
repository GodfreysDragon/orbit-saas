<?php

namespace App\Http\Controllers;

use App\Billing\SubscriptionSummary;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $current): Response
    {
        $organization = $current->getOrFail();
        $plan = $organization->plan();
        $canViewActivity = Gate::allows('viewActivity', $organization);

        $projectCount = Project::count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'members' => $organization->users()->count(),
                'pendingInvitations' => $organization->invitations()->where('expires_at', '>', now())->count(),
                'projects' => $projectCount,
                'activeProjects' => Project::where('status', ProjectStatus::Active)->count(),
            ],
            'usage' => [
                'members' => ['used' => $organization->seatsUsed(), 'limit' => $plan->maxMembers],
                'projects' => ['used' => $projectCount, 'limit' => $plan->maxProjects],
            ],
            'billing' => SubscriptionSummary::for($organization),
            'recentProjects' => Project::with('creator:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status->value,
                    'creator' => $project->creator?->name,
                    'createdAt' => $project->created_at?->toIso8601String(),
                ]),
            'activityTrend' => $canViewActivity ? Inertia::defer(fn () => $this->activityTrend()) : null,
            'recentActivity' => $canViewActivity ? Inertia::defer(fn () => Activity::with('causer:id,name')
                ->latest('created_at')
                ->limit(6)
                ->get()
                ->map(fn (Activity $activity) => ActivityController::present($activity))) : null,
            'onboarding' => [
                'project' => $projectCount > 0,
                'teammate' => $organization->users()->count() > 1 || $organization->invitations()->exists(),
                'plan' => $organization->subscribed(),
                'twoFactor' => $request->user()->two_factor_confirmed_at !== null,
            ],
        ]);
    }

    /**
     * Daily activity counts for the last 14 days, oldest first.
     *
     * @return list<array{date: string, count: int}>
     */
    private function activityTrend(): array
    {
        $start = now()->subDays(13)->startOfDay();

        $counts = Activity::where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $date) => $date->toDateString());

        return array_map(function (int $offset) use ($start, $counts): array {
            $date = $start->addDays($offset)->toDateString();

            return ['date' => $date, 'count' => $counts->get($date, 0)];
        }, range(0, 13));
    }
}
