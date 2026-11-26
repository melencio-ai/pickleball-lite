<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->string('messenger_url', 2048)->nullable();
            $table->json('booking_durations')->nullable();
            $table->unsignedSmallInteger('advance_days')->default(30);
            $table->unsignedSmallInteger('minimum_notice')->default(0);
            $table->text('cancellation_policy')->nullable();
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->string('surface_type', 16)->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->text('cancellation_policy')->nullable();
        });

        Schema::create('venue_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('mime', 32);
            $table->string('version', 64);
            $table->mediumText('content');
            $table->unique(['venue_id', 'kind']);
        });

        Schema::create('venue_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 120);
            $table->unique(['venue_id', 'date']);
        });

        Schema::create('venue_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('weekdays');
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->unsignedInteger('hourly_rate');
            $table->index(['venue_id', 'court_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_rates');
        Schema::dropIfExists('venue_closures');
        Schema::dropIfExists('venue_media');

        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('cancellation_policy'));
        Schema::table('courts', fn (Blueprint $table) => $table->dropColumn('surface_type'));
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn([
            'brand_color', 'map_url', 'messenger_url', 'booking_durations', 'advance_days',
            'minimum_notice', 'cancellation_policy',
        ]));
    }
};
