<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Invitation tokens are now stored as SHA-256 hashes. Hashing the existing
     * plain tokens in place keeps links that were already sent working.
     */
    public function up(): void
    {
        DB::table('organization_invitations')->orderBy('id')->chunkById(100, function ($invitations): void {
            foreach ($invitations as $invitation) {
                DB::table('organization_invitations')
                    ->where('id', $invitation->id)
                    ->update(['token' => hash('sha256', $invitation->token)]);
            }
        });
    }

    /**
     * Hashes can't be reversed, so pending invitations have to be sent again.
     */
    public function down(): void
    {
        DB::table('organization_invitations')->delete();
    }
};
