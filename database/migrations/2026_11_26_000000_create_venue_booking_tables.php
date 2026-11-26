<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('phone', 32);
            $table->string('timezone')->default('Asia/Manila');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role', 16)->default('staff');
        });

        Schema::create('venue_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->string('opens_at', 5);
            $table->string('closes_at', 5);
            $table->boolean('closed')->default(false);
            $table->unique(['venue_id', 'weekday']);
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('hourly_rate');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['venue_id', 'name']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->string('reference', 26)->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('customer_name', 100);
            $table->string('customer_phone', 32);
            $table->unsignedInteger('total');
            $table->string('source', 16);
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'starts_at']);
        });

        Schema::create('court_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 120);
            $table->timestamps();
            $table->index(['court_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_blocks');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('venue_hours');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('venues');
    }
};
