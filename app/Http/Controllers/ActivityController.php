<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(CurrentOrganization $current): Response
    {
        Gate::authorize('viewActivity', $current->getOrFail());

        $activities = Activity::with('causer:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Activity $activity) => self::present($activity));

        return Inertia::render('activity/Index', [
            'activities' => $activities,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'causer' => $activity->causer?->only('id', 'name'),
            'properties' => $activity->properties,
            'createdAt' => $activity->created_at->toIso8601String(),
        ];
    }
}
