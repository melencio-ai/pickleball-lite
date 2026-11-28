<?php

namespace App\Http\Controllers;

use App\Services\VenueNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        ['records'=>$records,'unread'=>$unread]=$this->listing($request);
        $preferences=DB::table('notification_preferences')->where('user_id',$request->user()->id)->pluck('enabled','category');
        return response()->view('platform.notifications',compact('records','unread','preferences'))->header('Cache-Control','private, no-store');
    }

    public function drawer(Request $request)
    {
        return response()->view('platform.partials.notification-drawer-content',$this->listing($request))
            ->header('Cache-Control','private, no-store')
            ->header('X-Pickle-Notification-Drawer','1');
    }

    private function listing(Request $request): array
    {
        $query=VenueNotifications::query($request->user());
        $unread=(clone $query)->whereNull('read_at')->count();
        $records=$query->join('venues','venues.id','=','venue_notifications.venue_id')
            ->select('venue_notifications.*','venues.slug','venues.name as venue_name')
            ->orderByDesc('venue_notifications.id')->paginate(25);
        return compact('records','unread');
    }

    private function marked(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'=>$message,
                'unread'=>VenueNotifications::query($request->user())->whereNull('read_at')->count(),
            ])->header('Cache-Control','private, no-store');
        }
        return back()->with('status',$message);
    }

    public function count(Request $request)
    {
        return response()->json(['unread'=>VenueNotifications::query($request->user())->whereNull('read_at')->count()])
            ->header('Cache-Control','private, no-store');
    }

    public function read(Request $request,int $record)
    {
        $query=VenueNotifications::query($request->user())->where('venue_notifications.id',$record);
        abort_unless((clone $query)->exists(),404);
        $query->whereNull('read_at')->update(['read_at'=>now()]);
        return $this->marked($request,'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        VenueNotifications::query($request->user())->whereNull('read_at')->update(['read_at'=>now()]);
        return $this->marked($request,'All notifications marked as read.');
    }

    public function preferences(Request $request)
    {
        $data=$request->validate(['enabled'=>'sometimes|array','enabled.*'=>'in:booking,payment,open_play,account,promotion']);
        foreach(['booking','payment','open_play','account','promotion'] as $category) {
            DB::table('notification_preferences')->updateOrInsert(['user_id'=>$request->user()->id,'category'=>$category],
                ['enabled'=>in_array($category,$data['enabled'] ?? [],true)]);
        }
        return back()->with('status','Notification preferences saved.');
    }
}
