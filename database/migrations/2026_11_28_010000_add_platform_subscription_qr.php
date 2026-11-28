<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_subscriptions', function (Blueprint $table) {
            $table->boolean('qr_managed')->default(false);
        });
        Schema::create('platform_subscription_qr_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('base_ciphertext')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
        DB::table('platform_subscription_qr_settings')->insert(['id'=>1,'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('subscription_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reference',32)->unique();
            $table->string('plan',20);
            $table->unsignedInteger('amount');
            $table->text('qr_ciphertext');
            $table->string('status',20)->default('awaiting_payment');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('subscription_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('review_note',500)->nullable();
            $table->timestamps();
            $table->index(['venue_subscription_id','status']);
            $table->index(['status','created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Subscription payment records are forward-only.');
    }
};
