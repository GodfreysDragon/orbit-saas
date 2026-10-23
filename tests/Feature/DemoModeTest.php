<?php

use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;

test('demo accounts can change their password outside demo mode', function () {
    [, $user] = organizationWithUser();
    $user->update(['email' => 'test@example.com']);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('demo mode blocks credential changes on the shared demo accounts', function () {
    config(['app.demo' => true]);
    [$organization, $user] = organizationWithUser();
    $user->update(['email' => 'test@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => 'Hijacked', 'email' => 'someone@else.com'])
        ->assertRedirect(route('profile.edit'));

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $this->actingAs($user)->delete(route('organization.destroy'));

    $user->refresh();
    expect($user->email)->toBe('test@example.com')
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and($organization->fresh())->not->toBeNull();
});

test('demo mode answers blocked JSON requests with a 403', function () {
    config(['app.demo' => true]);
    [, $user] = organizationWithUser();
    $user->update(['email' => 'admin@example.com']);

    $this->actingAs($user)
        ->getJson(route('passkey.registration-options'))
        ->assertForbidden();
});

test('demo mode leaves other accounts and other actions alone', function () {
    config(['app.demo' => true]);
    [, $owner] = organizationWithUser();
    $owner->update(['email' => 'test@example.com']);
    [, $visitor] = organizationWithUser(OrganizationRole::Owner);

    $this->actingAs($visitor)
        ->patch(route('profile.update'), ['name' => 'Visitor', 'email' => $visitor->email])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('projects.store'), ['name' => 'Still allowed'])
        ->assertSessionHasNoErrors();

    expect($visitor->refresh()->name)->toBe('Visitor')
        ->and(DB::table('projects')->where('name', 'Still allowed')->exists())->toBeTrue();
});

test('sign-ups skip email verification in demo mode', function () {
    config(['app.demo' => true]);

    $this->post(route('register.store'), [
        'name' => 'Demo Visitor',
        'email' => 'visitor@example.org',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'visitor@example.org')->first()?->hasVerifiedEmail())->toBeTrue();
});

test('sign-ups still verify their email outside demo mode', function () {
    $this->post(route('register.store'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.org',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'visitor@example.org')->first()?->hasVerifiedEmail())->toBeFalse();
});

test('demo:reset refuses to run outside demo mode', function () {
    $this->artisan('demo:reset')->assertFailed();
});

test('demo:reset rebuilds a file database from the seeders', function () {
    $path = tempnam(sys_get_temp_dir(), 'demo');

    // A separate process, so the test's in-memory connection is left alone.
    $reset = fn () => Process::env(['APP_ENV' => 'testing', 'APP_DEMO' => 'true', 'DB_CONNECTION' => 'sqlite', 'DB_URL' => '', 'DB_DATABASE' => $path])
        ->run([PHP_BINARY, 'artisan', 'demo:reset']);

    try {
        expect($reset()->successful())->toBeTrue()
            ->and($reset()->successful())->toBeTrue();

        config(['database.connections.demo' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '']]);

        expect(DB::connection('demo')->table('users')->orderBy('email')->pluck('email')->all())
            ->toBe(['admin@example.com', 'member@example.com', 'test@example.com', 'viewer@example.com'])
            ->and(file_exists($path.'.next'))->toBeFalse();
    } finally {
        DB::purge('demo');
        @unlink($path);
    }
});
