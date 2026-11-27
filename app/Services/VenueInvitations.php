<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VenueInvitations
{
    public static function issue(Venue $venue, string $email, string $role, User $actor): string
    {
        $token = Str::random(64);
        $email = strtolower(trim($email));
        DB::transaction(function () use ($venue,$email,$role,$actor,$token) {
            Venue::query()->lockForUpdate()->findOrFail($venue->id);
            self::authorizeIssuer($actor->fresh(),$venue,$role);
            DB::table('venue_invitations')->where('venue_id',$venue->id)->where('email',$email)
                ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]);
            DB::table('venue_invitations')->insert([
                'venue_id'=>$venue->id,'email'=>$email,'role'=>$role,'token_hash'=>hash('sha256',$token),
                'invited_by'=>$actor->id,'expires_at'=>now()->addHours(72),'created_at'=>now(),'updated_at'=>now(),
            ]);
            self::audit($actor,$venue,'invitation.issued',['email'=>$email,'role'=>$role]);
        },3);

        return route('app.invite.show',['token'=>$token]);
    }

    public static function find(string $token, bool $lock = false): object
    {
        $query = DB::table('venue_invitations')->where('token_hash',hash('sha256',$token))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at','>',now());
        if ($lock) $query->lockForUpdate();
        $invitation = $query->first();
        abort_unless($invitation,404);

        return $invitation;
    }

    public static function accept(string $token, ?User $user, array $data): User
    {
        return DB::transaction(function () use ($token,$user,$data) {
            $initial = self::find($token);
            $venue = Venue::query()->lockForUpdate()->findOrFail($initial->venue_id);
            $invite = self::find($token,true);
            self::authorizeIssuer(User::findOrFail($invite->invited_by),$venue,$invite->role);
            $existing = User::query()->whereRaw('LOWER(email) = ?',[$invite->email])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($user && $user->id === $existing->id,403);
                $member = $existing->venues()->where('venues.id',$venue->id)->first();
                if (!$member) {
                    $existing->venues()->attach($venue->id,['role'=>$invite->role]);
                } elseif (array_search($invite->role,['staff','manager','owner'],true) > array_search($member->pivot->role,['staff','manager','owner'],true)) {
                    $existing->venues()->updateExistingPivot($venue->id,['role'=>$invite->role]);
                }
                $accepted = $existing;
            } else {
                abort_if($user,403);
                if (empty($data['name']) || empty($data['password'])) {
                    throw ValidationException::withMessages(['name'=>'Enter your name and password to accept this invitation.']);
                }
                $accepted = $venue->users()->create([
                    'name'=>$data['name'],'email'=>$invite->email,'password'=>$data['password'],
                    'role'=>$invite->role === 'owner' ? 'owner' : 'staff',
                ]);
                $accepted->venues()->updateExistingPivot($venue->id,['role'=>$invite->role]);
            }
            DB::table('venue_invitations')->where('id',$invite->id)->update(['accepted_at'=>now(),'updated_at'=>now()]);
            self::audit($accepted,$venue,'invitation.accepted',['role'=>$invite->role]);

            return $accepted;
        },3);
    }

    private static function authorizeIssuer(User $actor, Venue $venue, string $role): void
    {
        abort_unless(in_array($role,['owner','manager','staff'],true),403);
        abort_unless($actor->platform_admin || ($role !== 'owner' &&
            $actor->venues()->where('venues.id',$venue->id)->wherePivot('role','owner')->exists()),403);
    }

    public static function audit(User $actor, ?Venue $venue, string $action, array $details = []): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_id'=>$actor->id,'venue_id'=>$venue?->id,'action'=>$action,
            'details'=>json_encode($details,JSON_THROW_ON_ERROR),'created_at'=>now(),
        ]);
    }
}
