<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_subscriptions', function(Blueprint $table) {
            $table->string('billing_plan',20)->default('monthly');
            $table->string('requested_plan',20)->nullable();
            $table->timestamp('promo_email_attempted_at')->nullable();
            $table->timestamp('promo_email_sent_at')->nullable();
            $table->timestamp('promo_redeemed_at')->nullable();
        });
        DB::table('venue_subscriptions')->whereNotNull('upgrade_requested_at')->update(['requested_plan'=>'monthly']);
        $document=DB::table('legal_documents')->where('slug','subscription-terms')->first();
        if($document) {
            $body=strtr($document->draft_body,[
                'The standard plan is PHP 1,000 per month per venue.'=>'The standard plans are PHP 1,000 per month or PHP 10,000 per year per venue.',
                'arrange the first monthly payment. Monthly maintenance is payable in advance for the paid period.'=>'arrange the first payment for the selected plan. Subscription fees are payable in advance for the paid period.',
                'The venue owner may request up to three 15-day extensions during the last five days of an active trial, for a maximum of 75 days.'=>'Any discretionary trial extension is offered only when available in the account.',
                'Only the PHP 1,000 monthly maintenance fee applies when the owner requests and completes paid activation.'=>'The selected monthly or yearly subscription fee applies when the owner requests and completes paid activation.',
            ]);
            DB::table('legal_documents')->where('slug','subscription-terms')->update(['draft_body'=>$body,'updated_at'=>now()]);
        }
        $privacy=DB::table('legal_documents')->where('slug','privacy')->first();
        if($privacy) DB::table('legal_documents')->where('slug','privacy')->update(['draft_body'=>$privacy->draft_body."\n\nOptional account offers may be delivered to verified owners based on account age and subscription eligibility. Owners can disable promotional communications in notification preferences. Offer delivery and redemption are recorded to prevent duplicates.",'updated_at'=>now()]);
    }

    public function down(): void
    {
        throw new RuntimeException('Subscription plans and promotion records are forward-only.');
    }
};
