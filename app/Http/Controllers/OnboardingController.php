<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\EmvPaymentQr;
use App\Services\Pricing;
use App\Services\Schedule;
use App\Services\TrialAccess;
use App\Services\VenueContext;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    private function record(Venue $venue,bool $lock=false): object
    {
        abort_unless($venue->self_service && VenueContext::onApp(),404);
        $q=DB::table('venue_onboarding')->where('venue_id',$venue->id);
        if($lock) $q->lockForUpdate();
        return $q->firstOrFail();
    }

    public function show(?int $step=null)
    {
        $venue=VenueContext::venue();
        $onboarding=$this->record($venue);
        if($onboarding->completed_at) return VenueContext::redirect('staff.calendar');
        $step=min($step ?? $onboarding->step,$onboarding->step);
        abort_unless($step>=1 && $step<=7,404);
        $courts=$venue->courts()->orderBy('id')->get();
        $hours=$venue->hours()->get()->keyBy('weekday');
        $subscription=TrialAccess::subscription($venue);
        return view('platform.onboarding',compact('venue','step','onboarding','courts','hours','subscription'));
    }

    public function save(Request $request,int $step)
    {
        abort_unless($step>=1 && $step<=7,404);
        $venue=VenueContext::venue();
        DB::transaction(function() use($request,$step,$venue) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            $record=$this->record($venue,true);
            abort_unless(!$record->completed_at && !$venue->published && $venue->status==='onboarding' && $step<=$record->step,409);
            switch($step) {
                case 1:
                    $data=$request->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255',
                        'phone'=>['required','regex:'.Schedule::PHONE],'timezone'=>'required|timezone:all',
                        'slug'=>['required','string','max:100','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',Rule::unique('venues','slug')->ignore($venue->id),
                            Rule::notIn(['up','assets','demo-gallery','branding','book','open-play','staff','login','admin','venues','terms','privacy','register'])]]);
                    $venue->update($data);
                    break;
                case 2:
                    $data=$request->validate(['court_count'=>'required|integer|min:1|max:30']);
                    $count=(int)$data['court_count'];
                    $courts=$venue->courts()->orderBy('id')->get();
                    // No bookings can be created before launch; refuse any unexpected populated court.
                    foreach($courts->slice($count) as $court) {
                        if($court->bookings()->exists() || $court->blocks()->exists()) throw ValidationException::withMessages(['court_count'=>'A court has existing records. Use settings to manage it.']);
                        $court->delete();
                    }
                    for($i=$courts->count();$i<$count;$i++) $venue->courts()->create(['name'=>'Court '.($i+1),'hourly_rate'=>40000,'active'=>true]);
                    break;
                case 3:
                    $data=$request->validate(['days'=>'required|array|min:1|max:7','days.*'=>'integer|distinct|between:0,6',
                        'opens_at'=>['required','regex:'.Schedule::START_TIME],'closes_at'=>['required','regex:'.Schedule::END_TIME]]);
                    if($data['opens_at'] >= $data['closes_at']) throw ValidationException::withMessages(['closes_at'=>'Closing time must be after opening time.']);
                    foreach(range(0,6) as $day) $venue->hours()->updateOrCreate(['weekday'=>$day],
                        ['opens_at'=>$data['opens_at'],'closes_at'=>$data['closes_at'],'closed'=>!in_array($day,array_map('intval',$data['days']),true)]);
                    break;
                case 4:
                    $data=$request->validate(['rate'=>['required','regex:'.Pricing::PESOS],'durations'=>'required|array|min:1|max:9',
                        'durations.*'=>['integer','distinct',Rule::in(Venue::BOOKING_DURATIONS)]]);
                    if(Pricing::centavos($data['rate'])<=0) throw ValidationException::withMessages(['rate'=>'Enter a positive court rate.']);
                    $venue->courts()->update(['hourly_rate'=>Pricing::centavos($data['rate'])]);
                    $venue->update(['booking_durations'=>array_map('intval',$data['durations'])]);
                    break;
                case 5:
                    $enabled=$request->input('payment_method')==='qr';
                    $data=$request->validate(['payment_method'=>'required|in:venue,qr',
                        'payment_qr_base'=>[Rule::requiredIf($enabled),'nullable','string','max:512'],
                        'payment_messenger_url'=>[Rule::requiredIf($enabled),'nullable','regex:~^https://(?:m\.me/|(?:www\.)?facebook\.com/messages/t/)[a-zA-Z0-9._-]+/?$~D','max:255'],
                        'provider_confirmed'=>$enabled?['required','accepted']:['sometimes','accepted']]);
                    if($enabled) {
                        try {app(EmvPaymentQr::class)->generate($data['payment_qr_base'],100);}
                        catch(\InvalidArgumentException $e){throw ValidationException::withMessages(['payment_qr_base'=>$e->getMessage()]);}
                    }
                    $venue->update(['qr_payments_enabled'=>$enabled,'payment_qr_base'=>$enabled?$data['payment_qr_base']:null,
                        'payment_messenger_url'=>$enabled?$data['payment_messenger_url']:null]);
                    break;
                case 6:
                    $data=$request->validate(['brand_color'=>['nullable','regex:/^#[a-fA-F0-9]{6}$/D'],
                        'messenger_url'=>'nullable|url:https|max:2048',
                        'logo'=>'nullable|file|mimetypes:image/jpeg,image/png,image/webp|dimensions:max_width=4096,max_height=4096|max:1024',
                        'cover'=>'nullable|file|mimetypes:image/jpeg,image/png,image/webp|dimensions:max_width=4096,max_height=4096|max:2048']);
                    $venue->update(collect($data)->only(['brand_color','messenger_url'])->all());
                    foreach(['logo','cover'] as $kind) if(isset($data[$kind])) {
                        $file=$data[$kind];$content=$file->get();
                        $venue->media()->updateOrCreate(['kind'=>$kind],['mime'=>$file->getMimeType(),'version'=>hash('sha256',$content),'content'=>base64_encode($content)]);
                    }
                    break;
                case 7:
                    $data=$request->validate(['cancellation_policy'=>'required|string|max:1500','launch_confirmed'=>'accepted']);
                    abort_unless($request->user()->email_verified_at,403);
                    if(!TrialAccess::allowed($venue)) throw ValidationException::withMessages(['subscription'=>'Activate a subscription before launching.']);
                    if(!$venue->courts()->where('active',true)->exists() || !$venue->hours()->where('closed',false)->exists() ||
                        !$venue->address || !$venue->phone || ($venue->qr_payments_enabled && (!$venue->payment_qr_base || !$venue->payment_messenger_url))) {
                        throw ValidationException::withMessages(['launch_confirmed'=>'Complete your venue, courts, hours and payment settings before publishing.']);
                    }
                    $venue->update(['cancellation_policy'=>$data['cancellation_policy'],'published'=>true,'status'=>'active']);
                    DB::table('venue_onboarding')->where('venue_id',$venue->id)->update(['completed_at'=>now(),'updated_at'=>now()]);
                    VenueInvitations::audit($request->user(),$venue,'self_service.launched');
                    return;
            }
            DB::table('venue_onboarding')->where('venue_id',$venue->id)->update(['step'=>max($record->step,$step+1),'updated_at'=>now()]);
        },3);
        $venue->refresh();
        if($step===7) return redirect()->route('app.staff.calendar',['venueSlug'=>$venue->slug])->with('status','Your venue website is live!')->with('walkthrough_venue_id',$venue->id);
        return redirect()->route('app.settings.onboarding',['venueSlug'=>$venue->slug,'step'=>$step+1])->with('status','Progress saved.');
    }
}
