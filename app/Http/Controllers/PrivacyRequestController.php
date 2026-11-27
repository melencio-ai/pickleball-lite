<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\VenueContext;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PrivacyRequestController extends Controller
{
    private const KINDS = ['access','correction','erasure','objection','portability','other'];
    private const STATUSES = ['received','verifying','in_progress','resolved','declined'];

    private function scopedVenue(): ?Venue
    {
        return request()->routeIs('app.admin.privacy.*') ? null : VenueContext::venue();
    }

    private function query(?Venue $venue)
    {
        return DB::table('privacy_requests')->when($venue,fn ($q)=>$q->where('privacy_requests.venue_id',$venue->id));
    }

    private function destination(?Venue $venue): string
    {
        return $venue ? VenueContext::url('settings.privacy') : route('app.admin.privacy.index');
    }

    public function index(Request $request)
    {
        $venue = $this->scopedVenue();
        $status = $request->validate(['status'=>['nullable',Rule::in(self::STATUSES)]])['status'] ?? null;
        $records = $this->query($venue)->leftJoin('venues','venues.id','=','privacy_requests.venue_id')
            ->select('privacy_requests.*','venues.name as venue_name')
            ->when($status,fn ($q)=>$q->where('privacy_requests.status',$status))
            ->orderByDesc('privacy_requests.id')->paginate(25)->withQueryString();
        $venues = $venue ? collect() : Venue::orderBy('name')->get(['id','name']);
        $kinds = self::KINDS;
        $statuses = self::STATUSES;
        $destination = $this->destination($venue);
        return response()->view('legal.privacy-requests',compact('venue','records','venues','kinds','statuses','status','destination'))->header('Cache-Control','private, no-store');
    }

    public function store(Request $request)
    {
        $venue = $this->scopedVenue();
        $data = $request->validate([
            'kind'=>['required',Rule::in(self::KINDS)],'contact'=>'required|string|max:255',
            'received_on'=>'required|date_format:Y-m-d|before_or_equal:today',
            'venue_id'=> $venue ? ['prohibited'] : ['nullable','integer','exists:venues,id'],
        ]);
        $recordVenue = $venue ?: (isset($data['venue_id']) ? Venue::findOrFail($data['venue_id']) : null);
        DB::transaction(function () use ($request,$data,$recordVenue) {
            $id = DB::table('privacy_requests')->insertGetId([
                'venue_id'=>$recordVenue?->id,'kind'=>$data['kind'],'contact'=>$data['contact'],'received_on'=>$data['received_on'],
                'status'=>'received','recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            VenueInvitations::audit($request->user(),$recordVenue,'privacy.request_recorded',['request_id'=>$id,'kind'=>$data['kind']]);
        },3);
        return redirect()->to($this->destination($venue))->with('status','Privacy request recorded. Verify identity before releasing or changing data.');
    }

    public function update(Request $request,int $record)
    {
        $venue = $this->scopedVenue();
        $data = $request->validate(['status'=>['required',Rule::in(self::STATUSES)]]);
        DB::transaction(function () use ($request,$venue,$record,$data) {
            $row = $this->query($venue)->where('privacy_requests.id',$record)->lockForUpdate()->firstOrFail();
            DB::table('privacy_requests')->where('id',$row->id)->update(['status'=>$data['status'],'updated_at'=>now()]);
            VenueInvitations::audit($request->user(),$row->venue_id ? Venue::findOrFail($row->venue_id) : null,'privacy.request_status',['request_id'=>$row->id,'status'=>$data['status']]);
        },3);
        return back()->with('status','Request status updated. No customer data was automatically changed.');
    }
}
