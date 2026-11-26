<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('price');
            $table->string('skill_level', 20)->default('all');
            $table->string('series_id', 26)->nullable()->index();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'starts_at']);
        });
        Schema::create('open_play_session_courts', function (Blueprint $table) {
            $table->foreignId('open_play_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->primary(['open_play_session_id', 'court_id']);
        });
        Schema::table('court_blocks', function (Blueprint $table) {
            $table->foreignId('open_play_session_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('open_play_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_play_session_id')->constrained()->restrictOnDelete();
            $table->string('reference', 26)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 32);
            $table->unsignedInteger('total');
            $table->string('source', 10);
            $table->string('payment_token', 48)->unique();
            $table->text('payment_qr_payload');
            $table->string('payment_messenger_url');
            $table->dateTime('payment_expires_at');
            $table->string('payment_reference', 64)->nullable()->unique();
            $table->unsignedBigInteger('payment_verified_by')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();
            $table->index(['open_play_session_id', 'cancelled_at', 'payment_expires_at']);
        });
        Schema::create('payment_references', function (Blueprint $table) {
            $table->string('reference', 64)->primary();
        });
        DB::table('bookings')->whereNotNull('payment_reference')->orderBy('id')
            ->chunkById(500, function ($rows) {
                DB::table('payment_references')->insertOrIgnore($rows->map(
                    fn ($row) => ['reference' => strtoupper($row->payment_reference)]
                )->all());
            });
    }

    public function down(): void
    {
        // Do not use destructive schema rollback after Open Play has live registrations.
        Schema::dropIfExists('payment_references');
        Schema::dropIfExists('open_play_registrations');
        Schema::table('court_blocks', fn (Blueprint $table) => $table->dropConstrainedForeignId('open_play_session_id'));
        Schema::dropIfExists('open_play_session_courts');
        Schema::dropIfExists('open_play_sessions');
    }
};
