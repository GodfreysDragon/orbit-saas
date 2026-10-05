<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Roles and permissions are defined in code (see App\Enums) and synced
     * by a command so deploys can run the same step without seeding.
     */
    public function run(): void
    {
        Artisan::call('permissions:sync');
    }
}
