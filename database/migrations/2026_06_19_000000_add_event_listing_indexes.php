<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Date filtering and "upcoming" sorting key off created_time
            // (which holds the event start time).
            $table->index('created_time');

            // Location filtering uses a lat/lng bounding box derived from the
            // selected city.
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_time']);
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
