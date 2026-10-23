<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the demo database from the seeders. The new database is built in
 * a separate file and swapped in, so requests in flight never see it half
 * built. This avoids migrate:fresh, which production prohibits.
 *
 * Only runs in demo mode (APP_DEMO=true) on SQLite.
 */
#[Signature('demo:reset')]
#[Description('Rebuild the demo database with fresh seed data')]
class ResetDemo extends Command
{
    public function handle(): int
    {
        $connection = DB::getDefaultConnection();

        if (! config('app.demo') || config("database.connections.{$connection}.driver") !== 'sqlite') {
            $this->components->error('demo:reset only runs in demo mode (APP_DEMO=true) on SQLite.');

            return self::FAILURE;
        }

        /** @var string $path */
        $path = config("database.connections.{$connection}.database");

        if ($path === ':memory:') {
            $this->components->error('demo:reset needs a file-based SQLite database.');

            return self::FAILURE;
        }

        $next = $path.'.next';

        if (file_exists($next)) {
            unlink($next);
        }
        touch($next);

        try {
            $this->useDatabase($connection, $next);
            $this->callSilently('migrate', ['--force' => true]);
            $this->callSilently('db:seed', ['--force' => true]);
        } finally {
            $this->useDatabase($connection, $path);
        }

        rename($next, $path);

        $this->components->info('Demo data reset.');

        return self::SUCCESS;
    }

    private function useDatabase(string $connection, string $path): void
    {
        DB::disconnect($connection);
        config(["database.connections.{$connection}.database" => $path]);
        DB::purge($connection);
    }
}
