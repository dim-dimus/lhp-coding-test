<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // The listing and calendar always filter on a created_time range and
            // sort/group by it, optionally narrowed by status and/or a lat/lng
            // bounding box (the city filter). A composite led by created_time
            // covers the range + sort AND carries status, latitude and longitude,
            // so every filter combination is answered straight from the index —
            // no per-row lookups into the multi-GB events table, which is what
            // made status/city-filtered queries slow at scale.
            $table->index(['created_time', 'status', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['created_time', 'status', 'latitude', 'longitude']);
        });
    }
};
