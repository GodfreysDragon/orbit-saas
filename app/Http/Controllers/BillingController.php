<?php

namespace App\Http\Controllers;

use App\Billing\Plan;
use App\Billing\SubscriptionSummary;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\Activity;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Invoice;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class BillingController extends Controller
{
    public function index(CurrentOrganization $current): Response
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        return Inertia::render('billing/Index', [
            'plans' => Plan::all()->values()->map(fn (Plan $plan) => $plan->toArray()),
            'billing' => SubscriptionSummary::for($organization),
            'stripeConfigured' => $this->stripeConfigured(),
            'invoices' => Inertia::defer(fn () => $this->invoices($organization)),
        ]);
    }

    /**
     * Start a Stripe Checkout session for a new subscription.
     */
    public function checkout(CheckoutRequest $request, CurrentOrganization $current): SymfonyResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        if ($organization->subscribed()) {
            return $this->swap($request, $current);
        }

        if (! $this->stripeConfigured() || $request->stripePrice() === null) {
            return $this->notConfigured();
        }

        $checkout = $organization->newSubscription('default', $request->stripePrice())
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.index').'?checkout=success',
                'cancel_url' => route('billing.index'),
                'metadata' => ['organization_id' => $organization->getKey()],
            ]);

        return Inertia::location($checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Move an existing subscription to a different plan or interval (prorated).
     */
    public function swap(CheckoutRequest $request, CurrentOrganization $current): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        $price = $request->stripePrice();
        abort_if($price === null, 422, 'This plan is not available for purchase.');

        $organization->subscription()->swap($price);

        Activity::record('billing.plan_changed', "Changed plan to {$request->plan()->name} ({$request->validated('interval')})");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your plan has been updated.')]);

        return to_route('billing.index');
    }

    public function cancel(CurrentOrganization $current): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        $organization->subscription()?->cancel();

        Activity::record('billing.canceled', 'Canceled the subscription at the end of the billing period');

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Your subscription will end at the close of the billing period.')]);

        return to_route('billing.index');
    }

    public function resume(CurrentOrganization $current): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        $subscription = $organization->subscription();

        if ($subscription?->onGracePeriod()) {
            $subscription->resume();

            Activity::record('billing.resumed', 'Resumed the subscription');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your subscription has been resumed.')]);

        return to_route('billing.index');
    }

    /**
     * Redirect to the Stripe customer portal (payment methods, invoices, tax ids).
     */
    public function portal(CurrentOrganization $current): SymfonyResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('manageBilling', $organization);

        if (! $this->stripeConfigured()) {
            return $this->notConfigured();
        }

        $organization->createOrGetStripeCustomer();

        return Inertia::location($organization->billingPortalUrl(route('billing.index')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoices(Organization $organization): array
    {
        if (! $this->stripeConfigured() || ! $organization->hasStripeId()) {
            return [];
        }

        try {
            return array_values($organization->invoices()->map(function (Invoice $invoice): array {
                $stripeInvoice = $invoice->asStripeInvoice();

                return [
                    'id' => $stripeInvoice->id,
                    'number' => $stripeInvoice->number,
                    'date' => $invoice->date()->toIso8601String(),
                    'total' => $invoice->total(),
                    'status' => $stripeInvoice->status,
                    'url' => $stripeInvoice->hosted_invoice_url,
                ];
            })->all());
        } catch (Throwable $e) {
            Log::warning('Unable to load invoices from Stripe.', ['exception' => $e->getMessage()]);

            return [];
        }
    }

    private function stripeConfigured(): bool
    {
        return filled(config('cashier.key')) && filled(config('cashier.secret'));
    }

    private function notConfigured(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Stripe is not configured. Add your keys and price ids to .env.'),
        ]);

        return to_route('billing.index');
    }
}
