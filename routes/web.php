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

Route::get('/', [VenueLandingController::class, 'index'])->name('landing');
Route::get('/demo-gallery/{image}.webp', [VenueLandingController::class, 'demoImage'])
    ->whereIn('image', array_keys(\App\Services\VenueWebsite::DEMO_GALLERY))->name('landing.demo-image');
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

Route::prefix('staff')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StaffAuthController::class, 'create'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
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

        Route::middleware('can:manage-venue')->group(function () {
            Route::get('/website', [VenueWebsiteController::class, 'index'])->name('settings.website');
            Route::patch('/website', [VenueWebsiteController::class, 'save'])->name('settings.website.save');
            Route::patch('/website/gallery/{kind}', [VenueWebsiteController::class, 'gallery'])->whereIn('kind', \App\Services\VenueWebsite::GALLERY)->name('settings.website.gallery');
            Route::get('/booking-setup', [VenueCustomizationController::class, 'index'])->name('settings.customization');
            Route::patch('/booking-setup/branding', [VenueCustomizationController::class, 'branding'])->name('settings.branding');
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
    });
});
