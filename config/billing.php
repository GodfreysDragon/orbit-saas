<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    |
    | New organizations receive a card-less "generic" Cashier trial during
    | which they get the limits of the configured trial plan.
    |
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    'trial_plan' => 'pro',

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Prices are display values in whole dollars. The Stripe price ids are what
    | Cashier subscribes to and what an active subscription is matched against.
    | A `null` limit means unlimited.
    |
    */

    'plans' => [

        'free' => [
            'name' => 'Free',
            'description' => 'For individuals trying things out.',
            'price' => ['monthly' => 0, 'yearly' => 0],
            'stripe_prices' => ['monthly' => null, 'yearly' => null],
            'limits' => ['members' => 2, 'projects' => 3],
            'features' => ['Up to 2 members', 'Up to 3 projects', 'Community support'],
        ],

        'pro' => [
            'name' => 'Pro',
            'description' => 'For growing teams that need more room.',
            'price' => ['monthly' => 29, 'yearly' => 290],
            'stripe_prices' => [
                'monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'yearly' => env('STRIPE_PRICE_PRO_YEARLY'),
            ],
            'limits' => ['members' => 10, 'projects' => 50],
            'features' => ['Up to 10 members', 'Up to 50 projects', 'Activity log', 'Email support'],
            'highlighted' => true,
        ],

        'business' => [
            'name' => 'Business',
            'description' => 'For organizations operating at scale.',
            'price' => ['monthly' => 99, 'yearly' => 990],
            'stripe_prices' => [
                'monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
                'yearly' => env('STRIPE_PRICE_BUSINESS_YEARLY'),
            ],
            'limits' => ['members' => null, 'projects' => null],
            'features' => ['Unlimited members', 'Unlimited projects', 'Activity log', 'Priority support'],
        ],

    ],

];
