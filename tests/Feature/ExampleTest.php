<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_landing_page_lists_plans_from_billing_config()
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('plans', count(config('billing.plans')))
                ->where('plans.0.key', 'free')
                ->where('trialDays', config('billing.trial_days'))
                ->where('trialPlan', 'Pro')
                ->where('canRegister', true));
    }
}
