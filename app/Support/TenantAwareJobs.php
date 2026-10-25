<?php

namespace App\Support;

use App\Contracts\NotTenantAware;
use App\Models\Organization;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

/**
 * Queued work runs inside the organization it was dispatched from.
 *
 * Dispatching stores the current organization id in the job payload. Before
 * the job runs, that organization is restored as the CurrentOrganization,
 * so global scopes, Activity::record() and permission checks behave as they
 * did in the request. Afterwards the previous tenant is put back, which
 * matters for the sync queue, where jobs run inside the request.
 *
 * Jobs implementing NotTenantAware run without a tenant.
 */
final class TenantAwareJobs
{
    public const string PAYLOAD_KEY = 'organization_id';

    /**
     * Tenants to restore once each running job finishes (jobs can nest on the sync queue).
     *
     * @var list<Organization|null>
     */
    private array $previous = [];

    public static function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(fn (): array => [
            self::PAYLOAD_KEY => app(CurrentOrganization::class)->id(),
        ]);

        $events->listen(JobProcessing::class, fn (JobProcessing $event) => app(self::class)->enter($event));
        $events->listen(JobAttempted::class, fn () => app(self::class)->leave());
    }

    public function enter(JobProcessing $event): void
    {
        $current = app(CurrentOrganization::class);
        $this->previous[] = $current->get();

        $payload = $event->job->payload();
        $organizationId = $payload[self::PAYLOAD_KEY] ?? null;

        if (! is_int($organizationId) || self::optedOut($payload)) {
            $current->set(null);

            return;
        }

        $organization = Organization::find($organizationId);

        if ($organization === null) {
            // The organization was deleted after the job was queued.
            $event->job->fail((new ModelNotFoundException)->setModel(Organization::class, [$organizationId]));

            return;
        }

        $current->set($organization);
    }

    public function leave(): void
    {
        if ($this->previous !== []) {
            // Resolved per call: the worker swaps the scoped instance between jobs.
            app(CurrentOrganization::class)->set(array_pop($this->previous));
        }
    }

    /**
     * The job class is only known once the payload is complete, so the
     * NotTenantAware opt-out is checked when the job runs.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function optedOut(array $payload): bool
    {
        $job = $payload['data']['commandName'] ?? null;

        return is_string($job) && is_a($job, NotTenantAware::class, true);
    }
}
