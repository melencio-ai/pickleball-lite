<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the legacy column for compatibility, but remove the charge.
        Schema::table('venue_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('setup_fee')->default(0)->change();
        });
        DB::table('venue_subscriptions')->where('setup_fee','!=',0)->update(['setup_fee'=>0,'updated_at'=>now()]);

        // Only edit known stock draft passages. Preserve custom text and immutable
        // published versions, acceptances and the historical payment ledger.
        $replacements = [
            'terms' => [
                'Staff access is by invitation. Keep your credentials private, use only authorized venue accounts and notify your administrator of suspected unauthorized access.' => 'Venue owners may create an account through self-service registration where available or accept an authorized invitation. Owners must be at least 18 and authorized to represent the venue. Staff access is by invitation. Keep your credentials private, use only authorized venue accounts and notify your administrator of suspected unauthorized access.',
            ],
            'privacy' => [
                'Staff accounts contain names, email addresses, password hashes, memberships and access records.' => 'Owner and staff accounts contain names, email addresses, password hashes, memberships and access records. Self-service registration records email verification, onboarding progress, subscription and trial status. Service notifications and their read status or preferences may be recorded to support account administration and booking operations.',
            ],
            'subscription-terms' => [
                'The standard advertised offer is PHP 3,000 one-time setup and PHP 1,000 monthly maintenance for a venue. The actual agreed price, setup deliverables, billing dates and any separately agreed services must be confirmed with the venue operator.' => 'The standard plan is PHP 1,000 per month per venue. Account creation, standard self-service setup and activation are free. There is no setup or activation fee. Any separately requested optional services must be expressly agreed before they are charged.',
                'Payments and renewal status are recorded manually. The agreed payment deadlines, cancellation notice, overdue procedure, setup and subscription refund rules, support commitments and export/termination arrangements are provided in the commercial conditions below.' => 'Payments and renewal status are recorded manually. No charge is made solely because a trial ends. The owner must request paid activation and arrange the first monthly payment. Monthly maintenance is payable in advance for the paid period. Cancellation stops future billing, subject to any amount already due. Refund requests for duplicate or incorrect subscription payments, undelivered services or other legal grounds will be reviewed. Cancellation does not automatically create a prorated refund. The agreed payment deadlines, cancellation notice, overdue procedure, support commitments and export/termination arrangements are provided in the commercial conditions below.',
                'The standard setup fee and monthly maintenance apply when the owner requests and completes paid activation.' => 'Only the PHP 1,000 monthly maintenance fee applies when the owner requests and completes paid activation. There is no setup or activation fee.',
            ],
        ];
        DB::transaction(function () use ($replacements) {
            foreach ($replacements as $slug=>$pairs) {
                $document = DB::table('legal_documents')->where('slug',$slug)->lockForUpdate()->first();
                if (!$document) continue;
                $body = strtr($document->draft_body,$pairs);
                if ($body !== $document->draft_body) {
                    DB::table('legal_documents')->where('slug',$slug)->update(['draft_body'=>$body,'updated_at'=>now()]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Subscription pricing changes are forward-only. Do not restore obsolete setup charges.');
    }
};
