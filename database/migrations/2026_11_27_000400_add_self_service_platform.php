<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users',function(Blueprint $table) {
            $table->boolean('self_registered')->default(false);
        });
        Schema::table('venues',function(Blueprint $table) {
            $table->boolean('self_service')->default(false);
        });
        Schema::table('venue_subscriptions',function(Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedTinyInteger('trial_extensions_count')->default(0);
            $table->timestamp('upgrade_requested_at')->nullable();
        });
        Schema::create('venue_onboarding',function(Blueprint $table) {
            $table->foreignId('venue_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('trial_extensions',function(Blueprint $table) {
            $table->id();
            $table->foreignId('venue_subscription_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->timestamp('previous_end');
            $table->timestamp('new_end');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['venue_subscription_id','sequence']);
        });
        Schema::create('venue_notifications',function(Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('category',24);
            $table->string('event_key',120);
            $table->string('title',120);
            $table->string('message',250);
            $table->string('destination',24)->default('calendar');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id','event_key']);
            $table->index(['user_id','read_at','id']);
        });
        Schema::create('notification_preferences',function(Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category',24);
            $table->boolean('enabled')->default(true);
            $table->primary(['user_id','category']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Self-service records are forward-only. Do not drop trial or acceptance history.');
    }
};
