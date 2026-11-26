<?php

use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\StaffCalendarController;
use App\Http\Controllers\VenueSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicBookingController::class, 'index'])->name('book');
Route::post('/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:20,1')->name('book.store');
Route::get('/bookings/confirmed', [PublicBookingController::class, 'confirmation'])->name('book.confirmed');

Route::prefix('staff')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StaffAuthController::class, 'create'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
        Route::get('/calendar', [StaffCalendarController::class, 'index'])->name('staff.calendar');
        Route::post('/bookings', [StaffCalendarController::class, 'book'])->name('staff.book');
        Route::patch('/bookings/{booking}/paid', [StaffCalendarController::class, 'paid'])->name('staff.paid');
        Route::patch('/bookings/{booking}/cancel', [StaffCalendarController::class, 'cancel'])->name('staff.cancel');
        Route::post('/blocks', [StaffCalendarController::class, 'block'])->name('staff.block');
        Route::delete('/blocks/{block}', [StaffCalendarController::class, 'unblock'])->name('staff.unblock');

        Route::middleware('can:manage-venue')->group(function () {
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
