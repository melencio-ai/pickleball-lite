<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role',20);
            $table->string('token_hash',64)->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['venue_id','email']);
        });
        Schema::create('venue_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status',20)->default('onboarding');
            $table->unsignedInteger('setup_fee')->default(300000);
            $table->unsignedInteger('monthly_fee')->default(100000);
            $table->date('renewal_date')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_subscription_id')->constrained()->restrictOnDelete();
            $table->string('kind',20);
            $table->unsignedInteger('amount');
            $table->string('reference',100);
            $table->date('received_on');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['venue_subscription_id','reference']);
        });
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action',80);
            $table->json('details')->nullable();
            $table->timestamp('created_at');
            $table->index(['venue_id','created_at']);
        });
        Schema::create('product_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('venue_name',100);
            $table->string('name',100);
            $table->string('email');
            $table->string('phone',32)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
        DB::table('venues')->orderBy('id')->chunkById(100, function ($venues) {
            foreach ($venues as $venue) {
                DB::table('venue_subscriptions')->insertOrIgnore([
                    'venue_id'=>$venue->id,'status'=>'onboarding','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Platform records are forward-only. Restore a verified backup instead of dropping them.');
    }
};
