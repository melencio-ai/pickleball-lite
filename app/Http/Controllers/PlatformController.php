<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\User;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformController extends Controller
{
    public function home()
    {
        return redirect()->to(\App\Services\AuthDestination::home(auth()->user()));
    }

    public function venues(Request $request)
    {
        $venues = $request->user()->venues()->orderBy('venues.name')->get();

        return view('platform.venues', compact('venues'));
    }

    public function demo()
    {
        $slug = config('pickle.demo_slug');
        $venue = $slug ? Venue::where('slug',$slug)->where('status','active')->where('published',true)->first() : null;

        return $venue ? redirect()->route('public.landing',['venueSlug'=>$venue->slug])
            : redirect()->to(route('product.home').'#get-started')->with('status','Ask us for a live demo.');
    }

    public function enquiry(Request $request)
    {
        $data = $request->validate([
            'venue_name'=>'required|string|max:100','name'=>'required|string|max:100',
            'email'=>'required|email|max:255','phone'=>'nullable|string|max:32',
            'message'=>'nullable|string|max:1000','website'=>'nullable|string|max:0',
        ]);
        unset($data['website']);
        DB::table('product_enquiries')->insert($data+['created_at'=>now(),'updated_at'=>now()]);

        return redirect()->to(route('product.home').'#get-started')->with('status','Thanks! Your enquiry has been received.');
    }

    public function admin(Request $request)
    {
        $search = $request->validate(['q'=>'nullable|string|max:100'])['q'] ?? '';
        $query = Venue::query()->leftJoin('venue_subscriptions','venues.id','=','venue_subscriptions.venue_id')
            ->select('venues.*','venue_subscriptions.status as billing_status','venue_subscriptions.renewal_date','venue_subscriptions.upgrade_requested_at','venue_subscriptions.requested_plan')
            ->withCount('members');
        if ($search !== '') $query->where(fn ($q) => $q->where('venues.name','like','%'.$search.'%')->orWhere('venues.slug','like','%'.$search.'%'));
        $venues = $query->orderBy('venues.name')->paginate(25)->withQueryString();
        $stats = [
            'venues'=>Venue::count(),'active'=>Venue::where('status','active')->where('published',true)->count(),
            'mrr'=>DB::table('venue_subscriptions')->where('status','active')->selectRaw("COALESCE(SUM(CASE WHEN billing_plan='yearly' THEN 1000000.0/12 WHEN billing_plan='yearly_promo' THEN 500000.0/12 ELSE monthly_fee END),0) AS total")->value('total'),
            'overdue'=>DB::table('venue_subscriptions')->where('status','overdue')->count(),
        ];
        $enquiries = DB::table('product_enquiries')->latest()->limit(20)->get();
        $audit = DB::table('platform_audit_logs')->latest('id')->limit(20)->get();

        return view('platform.admin',compact('venues','stats','search','enquiries','audit'));
    }

    public function createVenue(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:100',
            'slug'=>['required','string','max:100','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D','unique:venues,slug',
                Rule::notIn(['up','assets','demo-gallery','branding','book','open-play','staff','login','admin','venues'])],
            'address'=>'nullable|string|max:255','phone'=>'nullable|string|max:32',
            'timezone'=>'required|timezone:all','owner_email'=>'required|email|max:255',
        ]);
        $invite = DB::transaction(function () use ($data,$request) {
            $venue = Venue::create([
                'name'=>$data['name'],'slug'=>$data['slug'],'address'=>$data['address'] ?? '',
                'phone'=>$data['phone'] ?? '','timezone'=>$data['timezone'],'status'=>'onboarding','published'=>false,
            ]);
            foreach (range(0,6) as $day) {
                $venue->hours()->create(['weekday'=>$day,'opens_at'=>'07:00','closes_at'=>'22:00','closed'=>true]);
            }
            DB::table('venue_subscriptions')->insert(['venue_id'=>$venue->id,'status'=>'onboarding','created_at'=>now(),'updated_at'=>now()]);
            VenueInvitations::audit($request->user(),$venue,'venue.created');
            return VenueInvitations::issue($venue,$data['owner_email'],'owner',$request->user());
        },3);

        return redirect()->route('app.admin.index')->with('status','Venue created. Share the private owner invitation.')->with('invite_url',$invite);
    }

    public function ownerInvite(Request $request,int $venue)
    {
        $data = $request->validate(['email'=>'required|email|max:255']);
        $record = Venue::findOrFail($venue);
        $invite = VenueInvitations::issue($record,$data['email'],'owner',$request->user());

        return back()->with('status','New owner invitation created.')->with('invite_url',$invite);
    }

    public function updateStatus(Request $request,int $venue)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(['onboarding','active','suspended','cancelled'])],
            'published'=>'sometimes|boolean',
        ]);
        DB::transaction(function () use ($request,$data,$venue) {
            $record = Venue::query()->lockForUpdate()->findOrFail($venue);
            $publish = $data['status'] === 'active' && $request->boolean('published');
            if ($publish && $record->self_service && (!\App\Services\TrialAccess::allowed($record) ||
                !DB::table('venue_onboarding')->where('venue_id',$record->id)->whereNotNull('completed_at')->exists())) {
                throw ValidationException::withMessages(['published'=>'The self-service venue must complete onboarding and have active access.']);
            }
            if ($publish && (!$record->members()->wherePivot('role','owner')->exists()
                || !$record->courts()->where('active',true)->exists() || !$record->hours()->where('closed',false)->exists()
                || ($record->qr_payments_enabled && (!$record->payment_qr_base || !$record->payment_messenger_url)))) {
                throw ValidationException::withMessages(['published'=>'Before publishing, confirm an owner, an active court, opening hours and any required QR payment setup.']);
            }
            $record->update(['status'=>$data['status'],'published'=>$publish]);
            VenueInvitations::audit($request->user(),$record,'venue.status',['status'=>$data['status'],'published'=>$publish]);
        },3);

        return back()->with('status','Venue status updated.');
    }

    public function billing(Request $request,int $venue)
    {
        $record = Venue::findOrFail($venue);
        $subscription = DB::table('venue_subscriptions')->where('venue_id',$venue)->first();
        $payments = $subscription ? DB::table('subscription_payments')->where('venue_subscription_id',$subscription->id)->orderByDesc('received_on')->orderByDesc('id')->paginate(25) : collect();

        return view('platform.billing',compact('record','subscription','payments'));
    }

    public function saveBilling(Request $request,int $venue)
    {
        $data = $request->validate(['status'=>['required',Rule::in(['onboarding','trial','trial_expired','active','overdue','suspended','cancelled'])],
            'renewal_date'=>'nullable|date_format:Y-m-d','expected_renewal'=>'nullable|date_format:Y-m-d']);
        DB::transaction(function () use ($request,$data,$venue) {
            $record = Venue::query()->lockForUpdate()->findOrFail($venue);
            $current = \App\Services\TrialAccess::subscription($record,true);
            if ($data['status']==='trial' && (!$current || !$current->trial_ends_at || \Carbon\CarbonImmutable::parse($current->trial_ends_at)->lte(now()))) {
                throw ValidationException::withMessages(['status'=>'A trial can only remain active until its recorded expiration.']);
            }
            if (($current?->reward_managed || \App\Services\SubscriptionPlans::annual($current)) && ($data['expected_renewal'] ?? null)!==$current->renewal_date) {
                throw ValidationException::withMessages(['renewal_date'=>'The subscription changed. Refresh before saving.']);
            }
            if (($current?->reward_managed || \App\Services\SubscriptionPlans::annual($current)) && $data['status']==='active' && $current->renewal_date &&
                $current->renewal_date>now($record->timezone)->toDateString() &&
                ($data['renewal_date'] ?? '')<$current->renewal_date) {
                throw ValidationException::withMessages(['renewal_date'=>'Do not shorten an existing reward-covered term here. Reconcile it separately.']);
            }
            if (($current?->reward_managed || \App\Services\SubscriptionPlans::annual($current)) && $data['status']==='active' &&
                (!filled($data['renewal_date'] ?? null) || $data['renewal_date']<=now($record->timezone)->toDateString())) {
                throw ValidationException::withMessages(['renewal_date'=>'A reward-managed subscription requires a future renewal date.']);
            }
            if ($data['status']==='active' && $record->self_service && !$record->members()->wherePivot('role','owner')->whereNotNull('email_verified_at')->exists()) {
                throw ValidationException::withMessages(['status'=>'The owner must verify their email before paid activation.']);
            }
            DB::table('venue_subscriptions')->updateOrInsert(['venue_id'=>$venue],[
                'status'=>$data['status'],'renewal_date'=>$data['renewal_date'] ?? null,'updated_at'=>now(),
            ]);
            VenueInvitations::audit($request->user(),$record,'subscription.updated',$data);
            \App\Services\VenueNotifications::emit($record,'subscription','status-'.\Illuminate\Support\Str::ulid(),'Subscription updated','Your subscription status is now '.$data['status'].'.','subscription',true);
        },3);

        return back()->with('status','Subscription updated.');
    }

    public function recordPayment(Request $request,int $venue)
    {
        $data = $request->validate([
            'kind'=>['required',Rule::in(['monthly','yearly','yearly_promo'])],
            'amount'=>['required','regex:'.\App\Services\Pricing::PESOS],
            'reference'=>'required|string|max:100','received_on'=>'required|date_format:Y-m-d|before_or_equal:today',
        ]);
        $amount = \App\Services\Pricing::centavos($data['amount']);
        if ($amount <= 0) throw ValidationException::withMessages(['amount'=>'Enter a positive payment amount.']);
        DB::transaction(function () use ($request,$data,$amount,$venue) {
            $record = Venue::query()->lockForUpdate()->findOrFail($venue);
            $sub = DB::table('venue_subscriptions')->where('venue_id',$venue)->lockForUpdate()->first();
            abort_unless($sub,404);
            $ref = strtoupper(trim($data['reference']));
            if (DB::table('subscription_payments')->where('venue_subscription_id',$sub->id)->where('reference',$ref)->exists()) {
                throw ValidationException::withMessages(['reference'=>'This subscription payment is already recorded.']);
            }
            $changes=\App\Services\SubscriptionPlans::verifiedPayment($record,$sub,$data['kind'],$amount);
            DB::table('subscription_payments')->insert([
                'venue_subscription_id'=>$sub->id,'kind'=>$data['kind'],'amount'=>$amount,'reference'=>$ref,
                'received_on'=>$data['received_on'],'recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('venue_subscriptions')->where('id',$sub->id)->update($changes+['updated_at'=>now()]);
            VenueInvitations::audit($request->user(),$record,'subscription.payment_recorded',['amount'=>$amount,'kind'=>$data['kind']]);
            \App\Services\VenueNotifications::emit($record,'subscription','payment-'.hash('sha256',$ref),'Subscription payment recorded','Your verified subscription payment has been recorded.','subscription',true);
        },3);

        return back()->with('status',$data['kind']==='monthly'?'Subscription payment recorded. Update renewal date and status separately.':'Yearly payment recorded and renewal date updated.');
    }
}
