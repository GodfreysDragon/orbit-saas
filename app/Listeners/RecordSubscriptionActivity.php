<?php

namespace App\Listeners;

use App\Billing\Plan;
use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Cashier keeps the subscriptions table in sync with Stripe webhooks; this
 * queued listener adds a human-readable entry to the organization's audit log.
 */
class RecordSubscriptionActivity implements ShouldQueue
{
    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        $description = match ($type) {
            'customer.subscription.created' => 'Subscribed to the :plan plan',
            'customer.subscription.updated' => 'Subscription updated (:plan plan, :status)',
            'customer.subscription.deleted' => 'Subscription ended',
            default => null,
        };

        if ($description === null) {
            return;
        }

        $object = $event->payload['data']['object'];

        if ($type === 'customer.subscription.updated' && ($object['status'] ?? null) === 'past_due') {
            $description = 'Payment failed: the :plan plan is past due';
        }

        /** @var Organization|null $organization */
        $organization = Cashier::findBillable($object['customer'] ?? null);

        if ($organization === null) {
            return;
        }

        $plan = Plan::findByStripePrice($object['items']['data'][0]['price']['id'] ?? null);
        $eventId = $event->payload['id'] ?? null;

        // Stripe delivers events at least once, so a retried delivery
        // carries the same event id and is recorded only once.
        Activity::record(
            str_replace('customer.', 'billing.', $type),
            strtr($description, [
                ':plan' => $plan->name ?? 'unknown',
                ':status' => $object['status'] ?? 'unknown',
            ]),
            properties: ['stripe_subscription' => $object['id'] ?? null],
            organization: $organization,
            idempotencyKey: is_string($eventId) ? "stripe:{$eventId}" : null,
        );
    }
}
