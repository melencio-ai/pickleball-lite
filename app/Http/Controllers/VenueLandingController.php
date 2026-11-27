<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Pricing;
use App\Services\VenueWebsite;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class VenueLandingController extends Controller
{
    public function demoImage(Request $request, string $image)
    {
        abort_unless(array_key_exists($image, VenueWebsite::DEMO_GALLERY), 404);
        $path = resource_path('demo-gallery/'.$image.'.webp.b64');
        abort_unless(is_file($path), 404);
        $bytes = base64_decode(file_get_contents($path), true);
        abort_unless($bytes !== false && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP', 404);

        $response = response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->setEtag(hash('sha256', $bytes));
        $response->isNotModified($request);

        return $response;
    }

    public function index(Request $request, VenueWebsite $website, Pricing $pricing)
    {
        // Old shared booking links still reach the survey. Normal campaign
        // parameters do not turn the public home page into a booking form.
        $legacy = collect($request->query())->only(['step', 'date', 'duration', 'court', 'start'])
            ->filter(fn ($value) => is_string($value) && $value !== '');
        if ($legacy->isNotEmpty()) {
            return \App\Services\VenueContext::redirect('book', $legacy->all());
        }

        $venue = \App\Services\VenueContext::venue();
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
        $gallery = $media->toBase()->only(VenueWebsite::GALLERY);
        $isDemoGallery = $gallery->isEmpty() && ($content['show_demo_gallery'] ?? true);
        $galleryPhotos = [];
        foreach (VenueWebsite::GALLERY as $kind) {
            if ($gallery->has($kind)) {
                $galleryPhotos[] = [
                    'url' => \App\Services\VenueContext::url('book.image', ['kind' => $kind, 'version' => $gallery[$kind]->version]),
                    'alt' => $venue->name.' gallery photo '.(count($galleryPhotos) + 1),
                ];
            }
        }
        if ($isDemoGallery) {
            foreach (VenueWebsite::DEMO_GALLERY as $image => $label) {
                $galleryPhotos[] = [
                    'url' => \App\Services\VenueContext::url('landing.demo-image', ['image' => $image]),
                    'alt' => 'Illustrative demo: '.$label,
                ];
            }
        }
        $headline = $content['headline'] ?? 'Your next game starts here.';
        $description = $content['description'] ?? 'Play with friends, meet new players and enjoy your next pickleball game at '.$venue->name.'.';
        $seoTitle = $content['seo_title'] ?? $venue->name.' | Pickleball courts & Open Play';
        $seoDescription = $content['seo_description'] ?? 'Find court rates, opening hours and upcoming Open Play at '.$venue->name.'. Book your next game online.';

        return view('landing.index', compact('venue', 'content', 'media', 'courts', 'openingHours', 'rates',
            'today', 'closures', 'sessions', 'canJoin', 'facilities', 'galleryPhotos', 'isDemoGallery', 'headline', 'description',
            'seoTitle', 'seoDescription'));
    }
}
