<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Removes expired organization invitations (see OrganizationInvitation::prunable).
Schedule::command('model:prune')->daily();

// Public demo deployments start every hour from fresh seed data.
Schedule::command('demo:reset')->hourly()->when(fn (): bool => (bool) config('app.demo'));
