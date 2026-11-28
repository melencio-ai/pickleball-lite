<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class WorkspaceNavigation
{
    /** Navigation is derived from the authorized request, never a client-selected tenant. */
    public static function build(User $user, ?Venue $venue, ?array $rewards): array
    {
        $admin=Gate::forUser($user)->allows('platform-admin');
        $context=$venue ? 'venue' : ($admin && request()->routeIs('app.admin.*') ? 'platform' : 'account');
        $groups=[];
        if ($context==='venue') {
            $owner=Gate::forUser($user)->allows('manage-venue');
            $website=Gate::forUser($user)->allows('manage-website');
            $venueUrl=fn(string $name)=>VenueContext::url($name,venue:$venue);
            if ($owner && $venue->self_service && DB::table('venue_onboarding')->where('venue_id',$venue->id)->whereNull('completed_at')->exists()) {
                $groups['Getting started']=[self::item('onboarding','Resume onboarding','file',$venueUrl('settings.onboarding'),['app.settings.onboarding*'])];
            }
            $groups['Operations']=[
                self::item('calendar','Calendar','calendar',$venueUrl('staff.calendar'),['app.staff.calendar*']),
                self::item('open-play','Open Play','users',$venueUrl('staff.open-play'),['app.staff.open-play*']),
            ];
            $groups['Online booking']=[self::item('website','Venue website','globe',$venueUrl('landing'),[],external:true)];
            if ($website) $groups['Online booking'][]=self::item('website-settings','Website settings','panels',$venueUrl('settings.website'),['app.settings.website*']);
            if ($owner) {
                $groups['Online booking'][]=self::item('booking-setup','Booking setup','sliders',$venueUrl('settings.customization'),['app.settings.customization']);
                $membership=[];
                if ($rewards!==null) $membership[]=self::item('rewards','Rewards','flame',$venueUrl('settings.rewards'),['app.settings.rewards*'],drawer:'rewards',meta:$rewards['streak'].'d');
                $membership[]=self::item('subscription','Subscription','card',$venueUrl('settings.subscription'),['app.settings.subscription*']);
                $groups['Membership']=$membership;
                $groups['Administration']=[
                    self::item('venue-settings','Venue settings','settings',$venueUrl('settings'),['app.settings']),
                    self::item('venue-privacy','Privacy requests','shield',$venueUrl('settings.privacy'),['app.settings.privacy*']),
                ];
            }
        } elseif ($context==='platform') {
            $groups=[
                'Platform'=>[
                    self::item('platform-overview','Overview','grid',route('app.admin.index'),['app.admin.index','app.admin.billing*']),
                    self::item('platform-payments','Subscription payments','card',route('app.admin.subscription-payments.index'),['app.admin.subscription-payments.*']),
                    self::item('platform-rewards','Rewards','gift',route('app.admin.rewards.index'),['app.admin.rewards.*']),
                ],
                'Configuration'=>[self::item('platform-email','Email service','mail',route('app.admin.email.index'),['app.admin.email.*'])],
                'Compliance'=>[
                    self::item('platform-legal','Legal policies','file',route('app.admin.legal.index'),['app.admin.legal.*']),
                    self::item('platform-privacy','Privacy requests','shield',route('app.admin.privacy.index'),['app.admin.privacy.*']),
                ],
            ];
        } else {
            $groups=['Workspaces'=>[self::item('my-venues','My venues','grid',route('app.venues'),['app.venues'])]];
        }
        // Mobile destinations are a subset of the same server-authorized navigation.
        $primaryIds=match($context) {
            'venue'=>['calendar','open-play'],
            'platform'=>['platform-overview','platform-rewards'],
            default=>['my-venues'],
        };
        $mobile=array_values(array_filter(array_merge(...array_values($groups)),
            fn(array $item)=>in_array($item['id'],$primaryIds,true)));
        // Bounded quick switch list; the existing My venues page remains the full directory.
        $venues=$user->venues()->wherePivotIn('role',['owner','manager','staff'])
            ->orderByRaw('case when venues.id = ? then 0 else 1 end',[$venue?->id ?? 0])
            ->orderBy('venues.name')->orderBy('venues.id')->limit(21)
            ->get(['venues.id','venues.slug','venues.name','venues.status']);
        return [
            'context'=>$context,'groups'=>array_filter($groups),'mobile'=>$mobile,'admin'=>$admin,
            'venues'=>$venues->take(20),'more'=>$venues->count()>20,
            'label'=>$venue?->name ?? ($context==='platform'?'Pickle Lite':'My venues'),
            'description'=>match($context){'venue'=>ucfirst(VenueContext::role() ?? '').' workspace','platform'=>'Super Admin',default=>'Choose a workspace'},
        ];
    }

    private static function item(string $id,string $label,string $icon,string $href,array $patterns=[],bool $external=false,?string $drawer=null,?string $meta=null): array
    {
        return compact('id','label','icon','href','external','drawer','meta')+
            ['active'=>$patterns!==[] && request()->routeIs(...$patterns)];
    }
}
