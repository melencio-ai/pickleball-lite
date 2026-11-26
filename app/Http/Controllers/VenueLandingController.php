<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Pricing;
use App\Services\VenueWebsite;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class VenueLandingController extends Controller
{
    public function index(Request $request, VenueWebsite $website, Pricing $pricing)
    {
        // Old shared booking links still reach the survey. Normal campaign
        // parameters do not turn the public home page into a booking form.
        $legacy = collect($request->query())->only(['step', 'date', 'duration', 'court', 'start'])
            ->filter(fn ($value) => is_string($value) && $value !== '');
        if ($legacy->isNotEmpty()) {
            return redirect()->route('book', $legacy->all());
        }

        $venue = Venue::query()->orderBy('id')->first();
        if (!$venue) {
            return response()->view('book.setup', [], 503);
        }

        $content = $venue->website_content ?? [];
        $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');
        $courts = $venue->courts()->where('active', true)->orderBy('id')->get();
        $hours = $venue->hours()->get()->keyBy('weekday');
        $openingHours = $website->hours($hours);
        $rates = $website->rates($venue, $courts, $hours, $pricing);
        $today = CarbonImmutable::today($venue->timezone);
        $lastDay = $today->addDays($venue->advance_days);
        $closures = $venue->closures()->where('date', '>=', $today->toDateString())
            ->orderBy('date')->limit(10)->get();
        $sessions = $venue->openPlaySessions()->whereNull('cancelled_at')
            ->where('starts_at', '>', now())->where('starts_at', '<', $lastDay->addDay()->utc())
            ->withCount(['registrations as active_count' => fn ($query) => $query->occupying(), 'courts'])
            ->orderBy('starts_at')->limit(6)->get();
        $canJoin = $venue->qr_payments_enabled && $venue->payment_qr_base && $venue->payment_messenger_url;
        $facilities = collect(VenueWebsite::FACILITIES)
            ->only($content['facilities'] ?? [])->values()
            ->concat($content['custom_facilities'] ?? [])->unique()->values();
        $gallery = $media->only(VenueWebsite::GALLERY);
        $headline = $content['headline'] ?? 'Your next game starts here.';
        $description = $content['description'] ?? 'Play with friends, meet new players and enjoy your next pickleball game at '.$venue->name.'.';
        $seoTitle = $content['seo_title'] ?? $venue->name.' | Pickleball courts & Open Play';
        $seoDescription = $content['seo_description'] ?? 'Find court rates, opening hours and upcoming Open Play at '.$venue->name.'. Book your next game online.';
        $brand = preg_match('/^#[0-9a-fA-F]{6}$/D', $venue->brand_color ?? '') ? $venue->brand_color : '#155542';
        // Keep white text legible even when the owner selects a light brand color.
        $rgb = array_map(fn ($part) => hexdec($part) / 255, str_split(substr($brand, 1), 2));
        $luminance = array_sum(array_map(fn ($value, $weight) =>
            ($value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4) * $weight, $rgb, [0.2126, 0.7152, 0.0722]));
        $brandText = $luminance > 0.179 ? '#111111' : '#ffffff';

        return view('landing.index', compact('venue', 'content', 'media', 'courts', 'openingHours', 'rates',
            'today', 'closures', 'sessions', 'canJoin', 'facilities', 'gallery', 'headline', 'description',
            'seoTitle', 'seoDescription', 'brand', 'brandText'));
    }
}
