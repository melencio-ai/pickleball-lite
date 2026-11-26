<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->boolean('qr_payments_enabled')->default(false);
            $table->text('payment_qr_base')->nullable();
            $table->string('payment_messenger_url')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_token', 48)->nullable()->unique();
            $table->text('payment_qr_payload')->nullable();
            $table->string('payment_messenger_url')->nullable();
            $table->dateTime('payment_expires_at')->nullable();
            $table->string('payment_reference', 64)->nullable()->unique();
            $table->unsignedBigInteger('payment_verified_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['payment_token']);
            $table->dropUnique(['payment_reference']);
            $table->dropColumn([
                'payment_token', 'payment_qr_payload', 'payment_messenger_url', 'payment_expires_at',
                'payment_reference', 'payment_verified_by',
            ]);
        });
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn([
            'qr_payments_enabled', 'payment_qr_base', 'payment_messenger_url',
        ]));
    }
};
