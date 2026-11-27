<?php

use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\VenueLandingController;
use App\Http\Controllers\VenueWebsiteController;
use App\Http\Controllers\PublicOpenPlayController;
use App\Http\Controllers\StaffOpenPlayController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\StaffCalendarController;
use App\Http\Controllers\VenueCustomizationController;
use App\Http\Controllers\VenueSettingsController;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\LegacyHost;
use App\Http\Middleware\ResolvePublicVenue;
use App\Http\Middleware\ResolveStaffVenue;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\InvitationController;

$publicRoutes = function () {
    Route::get('/', [VenueLandingController::class, 'index'])->name('landing');
    Route::get('/book', [PublicBookingController::class, 'index'])->name('book');
    Route::post('/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:20,1')->name('book.store');
    Route::get('/branding/{kind}/{version}', [PublicBookingController::class, 'image'])
        ->whereIn('kind', array_merge(['logo', 'cover'], \App\Services\VenueWebsite::GALLERY))->where('version', '[a-f0-9]{64}')->name('book.image');
    Route::get('/payments/{token}/status', [PublicBookingController::class, 'paymentStatus'])
        ->where('token', '[a-zA-Z0-9]{48}')->middleware('throttle:booking-payment-status')->name('book.payment.status');
    Route::get('/payments/{token}', [PublicBookingController::class, 'payment'])
        ->where('token', '[a-zA-Z0-9]{48}')->name('book.payment');
    Route::get('/bookings/confirmed', [PublicBookingController::class, 'confirmation'])->name('book.confirmed');

    Route::get('/open-play', [PublicOpenPlayController::class, 'index'])->name('open-play');
    Route::post('/open-play/{session}/join', [PublicOpenPlayController::class, 'store'])->whereNumber('session')->middleware('throttle:20,1')->name('open-play.join');
    Route::get('/open-play/payments/{token}/status', [PublicOpenPlayController::class, 'paymentStatus'])->where('token', '[a-zA-Z0-9]{48}')->middleware('throttle:booking-payment-status')->name('open-play.payment.status');
    Route::get('/open-play/payments/{token}', [PublicOpenPlayController::class, 'payment'])->where('token', '[a-zA-Z0-9]{48}')->name('open-play.payment');

};

$staffRoutes = function () {
    Route::get('/calendar', [StaffCalendarController::class, 'index'])->name('staff.calendar');
    Route::get('/calendar/modal/{kind}/{record?}', [StaffCalendarController::class, 'modal'])->whereIn('kind', ['new', 'book', 'block', 'open-play', 'booking', 'blocked', 'session'])->whereNumber('record')->name('staff.calendar.modal');
    Route::post('/bookings', [StaffCalendarController::class, 'book'])->name('staff.book');
    Route::patch('/bookings/{booking}/paid', [StaffCalendarController::class, 'paid'])->name('staff.paid');
    Route::patch('/bookings/{booking}/cancel', [StaffCalendarController::class, 'cancel'])->name('staff.cancel');
    Route::post('/blocks', [StaffCalendarController::class, 'block'])->name('staff.block');
    Route::delete('/blocks/{block}', [StaffCalendarController::class, 'unblock'])->name('staff.unblock');

    Route::get('/open-play', [StaffOpenPlayController::class, 'index'])->name('staff.open-play');
    Route::post('/open-play', [StaffOpenPlayController::class, 'store'])->name('staff.open-play.store');
    Route::get('/open-play/{session}', [StaffOpenPlayController::class, 'show'])->whereNumber('session')->name('staff.open-play.show');
    Route::post('/open-play/{session}/registrations', [StaffOpenPlayController::class, 'register'])->whereNumber('session')->name('staff.open-play.register');
    Route::patch('/open-play/{session}/registrations/{registration}/{action}', [StaffOpenPlayController::class, 'action'])->whereNumber('session')->whereNumber('registration')->name('staff.open-play.action');
    Route::patch('/open-play/{session}/cancel', [StaffOpenPlayController::class, 'cancel'])->whereNumber('session')->name('staff.open-play.cancel');

    Route::middleware('can:manage-website')->group(function () {
        Route::get('/website', [VenueWebsiteController::class, 'index'])->name('settings.website');
        Route::get('/website/preview', [VenueLandingController::class, 'index'])->name('settings.website.preview');
        Route::patch('/website', [VenueWebsiteController::class, 'save'])->name('settings.website.save');
        Route::patch('/website/gallery/{kind}', [VenueWebsiteController::class, 'gallery'])->whereIn('kind', \App\Services\VenueWebsite::GALLERY)->name('settings.website.gallery');
    });
    Route::middleware('can:manage-venue')->group(function () {
        Route::get('/booking-setup', [VenueCustomizationController::class, 'index'])->name('settings.customization');
        Route::patch('/booking-setup/branding', [VenueCustomizationController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/staff/invite', [InvitationController::class, 'issueStaff'])->name('settings.staff.invite');
        Route::patch('/booking-setup/payments', [VenueCustomizationController::class, 'payment'])->name('settings.payments');
        Route::patch('/booking-setup/rules', [VenueCustomizationController::class, 'rules'])->name('settings.rules');
        Route::post('/booking-setup/closures', [VenueCustomizationController::class, 'closeDate'])->name('settings.closures.add');
        Route::delete('/booking-setup/closures/{closure}', [VenueCustomizationController::class, 'removeClosure'])->name('settings.closures.remove');
        Route::post('/booking-setup/rates', [VenueCustomizationController::class, 'saveRate'])->name('settings.rates.add');
        Route::patch('/booking-setup/rates/{rate}', [VenueCustomizationController::class, 'saveRate'])->name('settings.rates.update');
        Route::delete('/booking-setup/rates/{rate}', [VenueCustomizationController::class, 'removeRate'])->name('settings.rates.remove');
        Route::get('/settings', [VenueSettingsController::class, 'index'])->name('settings');
        Route::patch('/settings/venue', [VenueSettingsController::class, 'venue'])->name('settings.venue');
        Route::patch('/settings/hours', [VenueSettingsController::class, 'hours'])->name('settings.hours');
        Route::post('/settings/courts', [VenueSettingsController::class, 'addCourt'])->name('settings.court.add');
        Route::patch('/settings/courts/{court}', [VenueSettingsController::class, 'updateCourt'])->name('settings.court.update');
        Route::post('/settings/staff', [VenueSettingsController::class, 'addStaff'])->name('settings.staff.add');
        Route::delete('/settings/staff/{staff}', [VenueSettingsController::class, 'removeStaff'])->name('settings.staff.remove');
    });
};

Route::domain(config('pickle.product_host'))->group(function () {
    Route::view('/', 'platform.product')->name('product.home');
    Route::redirect('/features', '/#features');
    Route::redirect('/pricing', '/#pricing');
    Route::get('/demo', [PlatformController::class, 'demo'])->name('product.demo');
    Route::redirect('/get-started', '/#get-started');
    Route::post('/get-started', [PlatformController::class, 'enquiry'])->middleware('throttle:5,1')->name('product.enquiry');
});

Route::domain(config('pickle.book_host'))->name('public.')->group(function () use ($publicRoutes) {
    Route::view('/', 'platform.booking-home')->name('home');
    Route::get('/demo-gallery/{image}.webp', [VenueLandingController::class, 'demoImage'])
    ->whereIn('image', array_keys(\App\Services\VenueWebsite::DEMO_GALLERY))->name('demo-image');
    Route::prefix('{venueSlug}')->where(['venueSlug'=>'[a-z0-9]+(?:-[a-z0-9]+)*'])
        ->middleware(ResolvePublicVenue::class)->group($publicRoutes);
});

Route::domain(config('pickle.app_host'))->name('app.')->group(function () use ($staffRoutes) {
    Route::get('/', [PlatformController::class, 'home'])->name('home');
    Route::get('/invite/{token}', [InvitationController::class, 'show'])->where('token','[a-zA-Z0-9]{64}')->middleware('throttle:30,1')->name('invite.show');
    Route::post('/invite/{token}', [InvitationController::class, 'accept'])->where('token','[a-zA-Z0-9]{64}')->middleware('throttle:10,1')->name('invite.accept');
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StaffAuthController::class, 'create'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    });
    Route::middleware('auth')->group(function () use ($staffRoutes) {
        Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
        Route::get('/venues', [PlatformController::class, 'venues'])->name('venues');
        Route::prefix('venues/{venueSlug}')->where(['venueSlug'=>'[a-z0-9]+(?:-[a-z0-9]+)*'])
            ->middleware(ResolveStaffVenue::class)->group($staffRoutes);
        Route::middleware('can:platform-admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [PlatformController::class, 'admin'])->name('index');
            Route::post('/venues', [PlatformController::class, 'createVenue'])->name('venues.create');
            Route::patch('/venues/{venue}/status', [PlatformController::class, 'updateStatus'])->whereNumber('venue')->name('venues.status');
            Route::post('/venues/{venue}/invite', [PlatformController::class, 'ownerInvite'])->whereNumber('venue')->name('venues.invite');
            Route::get('/venues/{venue}/billing', [PlatformController::class, 'billing'])->whereNumber('venue')->name('billing');
            Route::patch('/venues/{venue}/billing', [PlatformController::class, 'saveBilling'])->whereNumber('venue')->name('billing.save');
            Route::post('/venues/{venue}/billing/payments', [PlatformController::class, 'recordPayment'])->whereNumber('venue')->name('billing.payment');
        });
    });
});

// Keep all historical URLs on the original service host until DNS cutover is complete.
Route::middleware(LegacyHost::class)->group(function () use ($publicRoutes,$staffRoutes) {
    Route::get('/demo-gallery/{image}.webp', [VenueLandingController::class, 'demoImage'])
    ->whereIn('image', array_keys(\App\Services\VenueWebsite::DEMO_GALLERY))->name('landing.demo-image');
    Route::middleware(ResolvePublicVenue::class.':legacy')->group($publicRoutes);
    Route::prefix('staff')->group(function () use ($staffRoutes) {
        Route::middleware('guest')->group(function () {
            Route::get('/login', [StaffAuthController::class, 'create'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
        });
        Route::middleware('auth')->group(function () use ($staffRoutes) {
            Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
            Route::middleware(ResolveStaffVenue::class.':legacy')->group($staffRoutes);
        });
    });
});
