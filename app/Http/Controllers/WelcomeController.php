<?php

namespace App\Http\Controllers;

use App\Billing\Plan;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'plans' => Plan::all()->values()->map(fn (Plan $plan) => $plan->toArray()),
            'trialDays' => config('billing.trial_days'),
            'trialPlan' => Plan::trial()->name,
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
