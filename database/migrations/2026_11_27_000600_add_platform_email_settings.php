<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('password_ciphertext');
            $table->boolean('enabled')->default(false);
            $table->boolean('registration_enabled')->default(false);
            $table->boolean('recovery_enabled')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kind',32);
            $table->string('status',16);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Email configuration and audit history are forward-only.');
    }
};
