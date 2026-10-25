<?php

use App\Contracts\NotTenantAware;
use App\Models\Organization;
use App\Models\Project;
use App\Support\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Records what a queued job sees: the current tenant and the projects the
 * organization scope lets it query.
 */
class RecordTenantJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(CurrentOrganization $current): void
    {
        Cache::put('job.tenant', $current->id());
        Cache::put('job.projects', Project::count());
    }
}

class RecordAcrossTenantsJob extends RecordTenantJob implements NotTenantAware {}

beforeEach(function () {
    $this->acme = Organization::factory()->create();
    $this->globex = Organization::factory()->create();
    Project::factory()->count(2)->for($this->acme)->create();
    Project::factory()->count(5)->for($this->globex)->create();
});

// Each dispatch is a statement inside a block closure: PendingDispatch queues
// the job when it is destroyed, which must happen before runAs() returns.

/**
 * Process one job from the database queue, as `php artisan queue:work` would.
 */
function workOneJob(): void
{
    test()->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);
}

test('queued jobs run inside the organization they were dispatched from', function () {
    app(CurrentOrganization::class)->runAs($this->acme, function () {
        RecordTenantJob::dispatch()->onConnection('database');
    });

    workOneJob();

    expect(Cache::get('job.tenant'))->toBe($this->acme->id)
        ->and(Cache::get('job.projects'))->toBe(2);
});

test('each job gets its own tenant on a long-running worker', function () {
    $current = app(CurrentOrganization::class);
    $current->runAs($this->acme, function () {
        RecordTenantJob::dispatch()->onConnection('database');
    });
    $current->runAs($this->globex, function () {
        RecordTenantJob::dispatch()->onConnection('database');
    });

    workOneJob();
    expect(Cache::get('job.tenant'))->toBe($this->acme->id);

    workOneJob();
    expect(Cache::get('job.tenant'))->toBe($this->globex->id)
        ->and(Cache::get('job.projects'))->toBe(5);
});

test('jobs dispatched without a tenant run without one', function () {
    RecordTenantJob::dispatch()->onConnection('database');

    workOneJob();

    expect(Cache::get('job.tenant'))->toBeNull()
        ->and(Cache::get('job.projects'))->toBe(7);
});

test('NotTenantAware jobs ignore the tenant they were dispatched from', function () {
    app(CurrentOrganization::class)->runAs($this->acme, function () {
        RecordAcrossTenantsJob::dispatch()->onConnection('database');
    });

    workOneJob();

    expect(Cache::get('job.tenant'))->toBeNull()
        ->and(Cache::get('job.projects'))->toBe(7);
});

test('jobs fail when their organization was deleted after dispatch', function () {
    app(CurrentOrganization::class)->runAs($this->acme, function () {
        RecordTenantJob::dispatch()->onConnection('database');
    });
    $this->acme->delete();

    workOneJob();

    expect(Cache::has('job.tenant'))->toBeFalse()
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('sync jobs see the tenant and leave the request\'s tenant in place', function () {
    $current = app(CurrentOrganization::class);
    $current->set($this->globex);

    $current->runAs($this->acme, function () use ($current) {
        RecordTenantJob::dispatchSync();

        expect($current->id())->toBe($this->acme->id);
    });

    expect(Cache::get('job.tenant'))->toBe($this->acme->id)
        ->and($current->id())->toBe($this->globex->id);
});
