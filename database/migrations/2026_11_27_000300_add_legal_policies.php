<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->string('slug',32)->primary();
            $table->string('title',100);
            $table->longText('draft_body');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });
        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('document_slug',32);
            $table->foreign('document_slug')->references('slug')->on('legal_documents')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->json('operator_details');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->unique(['document_slug','version']);
        });
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->restrictOnDelete();
            $table->string('subject_type',24);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action',24);
            $table->timestamp('accepted_at');
            $table->unique(['legal_document_version_id','subject_type','subject_id','action'],'legal_acceptance_unique');
            $table->index(['venue_id','subject_type','subject_id']);
        });
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind',24);
            $table->string('contact',255);
            $table->date('received_on');
            $table->string('status',24)->default('received');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['venue_id','status']);
        });
        Schema::table('open_play_registrations', function (Blueprint $table) {
            $table->text('cancellation_policy')->nullable();
        });
        foreach (config('legal.drafts') as $slug=>$document) {
            DB::table('legal_documents')->insert([
                'slug'=>$slug,'title'=>$document['title'],'draft_body'=>$document['body'],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Legal history is forward-only. Do not drop acceptance or privacy records.');
    }
};
