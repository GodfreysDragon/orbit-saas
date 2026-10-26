<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activities recorded from an external event (e.g. a Stripe webhook,
     * which can be delivered more than once) carry a key that may only be
     * recorded once.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('properties');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
