<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venue_subscriptions',function(Blueprint $table) {
            $table->boolean('reward_managed')->default(false);
        });
        Schema::create('venue_reward_progress',function(Blueprint $table) {
            $table->foreignId('venue_id')->primary()->constrained('venues')->cascadeOnDelete();
            $table->string('timezone',64);
            $table->date('last_active_date')->index();
            $table->unsignedInteger('current_streak');
            $table->unsignedInteger('longest_streak');
            $table->unsignedInteger('active_days');
            $table->timestamps();
        });
        Schema::create('venue_reward_days',function(Blueprint $table) {
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->date('activity_date')->index();
            $table->primary(['venue_id','activity_date']);
        });
        Schema::create('venue_reward_testimonials',function(Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->unique()->constrained('venues')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_reference',500);
            $table->boolean('marketing_consent')->default(false);
            $table->string('status',20)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('venue_reward_grants',function(Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->string('milestone',32);
            $table->unsignedTinyInteger('months');
            $table->unsignedInteger('monthly_value');
            $table->timestamp('earned_at')->index();
            $table->timestamp('applied_at')->nullable()->index();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status',20)->nullable();
            $table->date('previous_renewal')->nullable();
            $table->date('new_renewal')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason',500)->nullable();
            $table->unique(['venue_id','milestone']);
        });
        // Draft only: published policy versions remain immutable.
        $additions=[
            'privacy'=>"Platform Rewards\nVenue-level active dates and streak totals are recorded from authorized operational use, not background polling. The program also records submitted testimonial references, separate marketing permission, review decisions and subscription credit history. Videos sent through Messenger are handled by Meta. Daily activity is retained for up to 12 months for reporting; aggregate progress is retained during the service relationship. Reward and consent records are retained as needed for subscription reconciliation, legal obligations and disputes. Do not include player or banking details in testimonials.",
            'subscription-terms'=>"Platform Rewards\nAn eligible venue may earn one free subscription month for an approved genuine video testimonial after 15 distinct active days, one month for 100 consecutive active days, and two additional months for 300 consecutive active days. Each award is available once per venue; no cash value or transfer. Facebook reviews are optional and independent of rewards; opinions need not be positive. Rewards are credited against future subscription periods, not the existing trial or cash payment ledger. As an exception to the usual first-payment requirement, the owner may apply an available credit to an eligible renewal or to start subscription access after a trial ends. A reward-covered period ends at its renewal date unless renewed. The streak uses the venue time zone recorded on its first active day; missed days reset the current streak, not earned credits. Invalid or duplicate awards may be investigated and unused credits revoked with a recorded reason. Marketing use requires separate permission and disclosure of the incentive. No change to existing mandatory rights.",
        ];
        foreach($additions as $slug=>$addition) {
            $doc=DB::table('legal_documents')->where('slug',$slug)->first();
            if($doc && !str_contains($doc->draft_body,$addition)) DB::table('legal_documents')->where('slug',$slug)
                ->update(['draft_body'=>rtrim($doc->draft_body)."\n\n".$addition,'updated_at'=>now()]);
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Reward and subscription credit records are forward-only.');
    }
};
