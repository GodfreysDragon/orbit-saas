<?php

namespace App\Providers;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\TenantAwareJobs;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset at the start of every request and queued job.
        $this->app->scoped(CurrentOrganization::class);
        $this->app->singleton(TenantAwareJobs::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBilling();

        TenantAwareJobs::register($this->app->make(Dispatcher::class));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /** @var string|null $proxies */
        $proxies = config('app.trusted_proxies');

        if ($proxies) {
            TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Organizations, not users, are the Stripe customers.
     *
     * A failed renewal makes the subscription past_due while Stripe retries
     * the payment. The organization keeps its paid plan during those retries
     * and the app shows a banner. If they all fail, Stripe cancels the
     * subscription and the organization drops to Free.
     */
    protected function configureBilling(): void
    {
        Cashier::useCustomerModel(Organization::class);
        Cashier::keepPastDueSubscriptionsActive();
    }
}
