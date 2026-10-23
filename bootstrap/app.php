<?php

use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectDemoAccounts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Stripe webhooks are verified by signature (STRIPE_WEBHOOK_SECRET) instead.
        $middleware->validateCsrfTokens(except: ['stripe/*']);

        $middleware->alias([
            'organization' => EnsureOrganizationContext::class,
        ]);

        // Resolve the tenant before route model binding so bound models
        // (e.g. {project}) are already filtered by the organization scope.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureOrganizationContext::class,
        );

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ProtectDemoAccounts::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Render client errors inside the app instead of the bare framework page.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (! in_array($response->getStatusCode(), [403, 404, 410], true) || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Policy denial reasons (e.g. plan limits) are user-facing; 404 messages
            // from route model binding are not, as they name internal models.
            $message = $status !== 404 && $exception->getMessage() !== 'This action is unauthorized.'
                ? $exception->getMessage()
                : null;

            return Inertia::render('ErrorPage', ['status' => $status, 'message' => $message ?: null])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
