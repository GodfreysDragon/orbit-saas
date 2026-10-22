<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizations\AcceptInvitationController;
use App\Http\Controllers\Organizations\CurrentOrganizationController;
use App\Http\Controllers\Organizations\InvitationController;
use App\Http\Controllers\Organizations\MemberController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Guests can view an invitation and sign up or log in from it. Accepting it
// verifies the invited address, so it doesn't require a verified email first.
Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])->name('invitations.accept');
    Route::delete('invitations/{token}', [AcceptInvitationController::class, 'destroy'])->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Routes that work without an active organization.
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::put('organizations/{organization}/switch', [CurrentOrganizationController::class, 'update'])->name('organizations.switch');

    // Tenant routes: everything below runs inside the user's current organization.
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');

        Route::prefix('organization')->group(function () {
            Route::get('settings', [OrganizationController::class, 'edit'])->name('organization.edit');
            Route::patch('settings', [OrganizationController::class, 'update'])->name('organization.update');
            Route::delete('settings', [OrganizationController::class, 'destroy'])->name('organization.destroy');

            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('organization.invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
                ->name('organization.invitations.destroy');
        });

        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::post('checkout', [BillingController::class, 'checkout'])->name('checkout');
            Route::post('cancel', [BillingController::class, 'cancel'])->name('cancel');
            Route::post('resume', [BillingController::class, 'resume'])->name('resume');
            Route::get('portal', [BillingController::class, 'portal'])->name('portal');
        });
    });
});

require __DIR__.'/settings.php';
