<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LegalPolicies
{
    public const SLUGS = ['terms','privacy','cookies','subscription-terms'];

    public static function current(bool $lock = false): Collection
    {
        $query = DB::table('legal_documents')->orderBy('slug');
        if ($lock) $query->sharedLock();
        $ids = $query->pluck('current_version_id')->filter()->values();
        $versions = DB::table('legal_document_versions')->whereIn('id',$ids);
        if ($lock) $versions->sharedLock();
        return $versions->get()->keyBy('document_slug');
    }

    public static function context(?Venue $venue = null, bool $subscription = false, bool $lock = false): array
    {
        $versions = self::current($lock);
        $terms = $versions->get('terms');
        $privacy = $versions->get('privacy');
        $subscriptionVersion = $subscription ? $versions->get('subscription-terms') : null;
        $policy = $venue ? self::venuePolicy($venue) : null;
        $fingerprint = $terms ? hash('sha256', json_encode([
            $terms->id, $privacy?->id, $subscriptionVersion?->id, $venue?->id, $policy,
        ], JSON_THROW_ON_ERROR)) : null;

        return compact('terms','privacy','subscriptionVersion','policy','fingerprint');
    }

    public static function venuePolicy(Venue $venue): string
    {
        return trim((string) $venue->cancellation_policy) ?: 'No specific cancellation policy has been provided. Contact the venue for changes, refunds and applicable conditions. Your statutory rights remain available.';
    }

    public static function validate(array $data, array $context): void
    {
        if (!$context['terms']) return;
        $fingerprint = $data['legal_fingerprint'] ?? null;
        if (!is_string($fingerprint) || !hash_equals($context['fingerprint'], $fingerprint)) {
            throw ValidationException::withMessages(['legal_accepted'=>'The policies have changed. Review the current versions before continuing.']);
        }
        if (!in_array($data['legal_accepted'] ?? null, ['1',1,true,'on','yes'],true)) {
            throw ValidationException::withMessages(['legal_accepted'=>'Please agree to the current Terms of Use before continuing.']);
        }
        if ($context['subscriptionVersion'] && !in_array($data['subscription_accepted'] ?? null, ['1',1,true,'on','yes'],true)) {
            throw ValidationException::withMessages(['subscription_accepted'=>'Please agree to the venue subscription terms.']);
        }
    }

    public static function record(array $context, string $type, int $id, ?int $venueId = null, ?int $actorId = null): void
    {
        foreach ([['terms','accepted'],['privacy','notice_presented'],['subscriptionVersion','accepted']] as [$key,$action]) {
            $version = $context[$key];
            if (!$version) continue;
            // Subscription acceptance is scoped to the individual venue, not all venues of the owner.
            $subjectType = $key === 'subscriptionVersion' ? 'venue_subscription' : $type;
            $subjectId = $key === 'subscriptionVersion' ? $venueId : $id;
            if (!$subjectId) continue;
            DB::table('legal_acceptances')->insertOrIgnore([
                'legal_document_version_id'=>$version->id,'subject_type'=>$subjectType,
                'subject_id'=>$subjectId,'venue_id'=>$venueId,'accepted_by'=>$actorId,
                'action'=>$action,'accepted_at'=>now(),
            ]);
        }
    }

    public static function needsAcceptance(User $user): bool
    {
        $version = self::current()->get('terms');
        return $version && !DB::table('legal_acceptances')->where([
            'legal_document_version_id'=>$version->id,'subject_type'=>'user',
            'subject_id'=>$user->id,'action'=>'accepted',
        ])->exists();
    }

    public static function url(string $slug, ?int $version = null): string
    {
        return route('product.legal.show', ['document'=>$slug] + ($version ? ['version'=>$version] : []));
    }
}
