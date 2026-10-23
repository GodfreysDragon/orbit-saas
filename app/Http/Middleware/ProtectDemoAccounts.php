<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * In demo mode, visitors share the seeded accounts. Blocks the changes that
 * would lock other visitors out of them or remove them.
 */
class ProtectDemoAccounts
{
    /**
     * @var list<string>
     */
    private const array BLOCKED_ROUTES = [
        'profile.update',
        'profile.destroy',
        'user-password.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.registration-options',
        'passkey.store',
        'passkey.destroy',
        'organization.destroy',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.demo') || ! $this->isDemoAccount($request) || ! $request->routeIs(...self::BLOCKED_ROUTES)) {
            return $next($request);
        }

        $message = __('This is disabled for the shared demo accounts. Sign up for your own account to try it.');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);

        return back();
    }

    private function isDemoAccount(Request $request): bool
    {
        /** @var list<string> $accounts */
        $accounts = config('app.demo_accounts');

        return in_array($request->user()?->email, $accounts, true);
    }
}
