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
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LegalAdminController;
use App\Http\Controllers\PrivacyRequestController;
use App\Http\Middleware\RequireLegalTerms;
use App\Http\Middleware\RequireVerifiedOwner;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\NotificationController;

require __DIR__.'/help.php';
// Preserve the existing robots response on other domains; help has its own sitemap.
Route::get('/robots.txt',fn()=>response("User-agent: *\nDisallow:\n",200,['Content-Type'=>'text/plain; charset=UTF-8']))->withoutMiddleware('web');

$publicRoutes = function () {
    Route::get('/', [VenueLandingController::class, 'index'])->name('landing');
    Route::get('/policies', [LegalController::class,'venue'])->name('policies');
    Route::get('/book', [PublicBookingController::class, 'index'])->name('book');
    Route::post('/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:20,1,book-store:')->name('book.store');
    Route::get('/branding/{kind}/{version}', [PublicBookingController::class, 'image'])
        ->whereIn('kind', array_merge(['logo', 'cover'], \App\Services\VenueWebsite::GALLERY))->where('version', '[a-f0-9]{64}')->name('book.image');
    Route::get('/payments/{token}/status', [PublicBookingController::class, 'paymentStatus'])
        ->where('token', '[a-zA-Z0-9]{48}')->middleware('throttle:booking-payment-status')->name('book.payment.status');
    Route::get('/payments/{token}', [PublicBookingController::class, 'payment'])
        ->where('token', '[a-zA-Z0-9]{48}')->name('book.payment');
    Route::get('/bookings/confirmed', [PublicBookingController::class, 'confirmation'])->name('book.confirmed');

    Route::get('/open-play', [PublicOpenPlayController::class, 'index'])->name('open-play');
    Route::post('/open-play/{session}/join', [PublicOpenPlayController::class, 'store'])->whereNumber('session')->middleware('throttle:20,1,open-play-join:')->name('open-play.join');
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
        Route::get('/onboarding/{step?}', [OnboardingController::class,'show'])->whereNumber('step')->name('settings.onboarding');
        Route::post('/onboarding/{step}', [OnboardingController::class,'save'])->whereNumber('step')->middleware('throttle:30,1,settings-onboarding-save:')->name('settings.onboarding.save');
        Route::get('/rewards', [\App\Http\Controllers\RewardsController::class,'index'])->name('settings.rewards');
        Route::post('/rewards/testimonial', [\App\Http\Controllers\RewardsController::class,'submit'])->middleware('throttle:5,10,rewards-submit:')->name('settings.rewards.submit');
        Route::post('/rewards/withdraw-marketing', [\App\Http\Controllers\RewardsController::class,'withdraw'])->name('settings.rewards.withdraw');
        Route::post('/rewards/{grant}/apply', [\App\Http\Controllers\RewardsController::class,'apply'])->whereNumber('grant')->middleware('throttle:5,10,rewards-apply:')->name('settings.rewards.apply');
        Route::get('/subscription', [SubscriptionController::class,'index'])->name('settings.subscription');
        Route::post('/subscription/extend', [SubscriptionController::class,'extend'])->middleware('throttle:5,1,settings-subscription-extend:')->name('settings.subscription.extend');
        Route::post('/subscription/upgrade', [SubscriptionController::class,'upgrade'])->middleware('throttle:5,1,settings-subscription-upgrade:')->name('settings.subscription.upgrade');
        Route::get('/privacy-requests', [PrivacyRequestController::class,'index'])->name('settings.privacy');
        Route::post('/privacy-requests', [PrivacyRequestController::class,'store'])->name('settings.privacy.store');
        Route::patch('/privacy-requests/{record}', [PrivacyRequestController::class,'update'])->whereNumber('record')->name('settings.privacy.update');
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
    Route::get('/{document}', [LegalController::class,'show'])->whereIn('document',\App\Services\LegalPolicies::SLUGS)->name('product.legal.show');
    Route::redirect('/features', '/#features');
    Route::redirect('/pricing', '/#pricing');
    Route::get('/demo', [PlatformController::class, 'demo'])->name('product.demo');
    Route::redirect('/get-started', '/#get-started');
    Route::post('/get-started', [PlatformController::class, 'enquiry'])->middleware('throttle:5,1,product-enquiry:')->name('product.enquiry');
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
    Route::get('/invite/{token}', [InvitationController::class, 'show'])->where('token','[a-zA-Z0-9]{64}')->middleware('throttle:30,1,invite-show:')->name('invite.show');
    Route::post('/invite/{token}', [InvitationController::class, 'accept'])->where('token','[a-zA-Z0-9]{64}')->middleware('throttle:10,1,invite-accept:')->name('invite.accept');
    Route::middleware('guest')->group(function () {
        Route::get('/register',[RegistrationController::class,'create'])->name('register');
        Route::post('/register',[RegistrationController::class,'store'])->middleware('throttle:5,60,register-store:')->name('register.store');
        Route::get('/login', [StaffAuthController::class, 'create'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1,login-store:')->name('login.store');
        Route::get('/forgot-password', [PasswordRecoveryController::class,'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordRecoveryController::class,'send'])->middleware('throttle:5,1,password-email:')->name('password.email');
        Route::get('/reset-password/{token}', [PasswordRecoveryController::class,'reset'])->where('token','[a-f0-9]{64}')->name('password.reset');
        Route::post('/reset-password', [PasswordRecoveryController::class,'update'])->middleware('throttle:10,1,password-update:')->name('password.update');
    });
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
        Route::get('/verify-email',[RegistrationController::class,'notice'])->name('verify.notice');
        Route::post('/verify-email/resend',[RegistrationController::class,'resend'])->middleware('throttle:5,10,verify-resend:')->name('verify.resend');
        Route::get('/verify-email/{id}/{hash}',[RegistrationController::class,'verify'])->whereNumber('id')->where('hash','[a-f0-9]{40}')->middleware(['signed','throttle:10,1,verify-complete:'])->name('verify.complete');
        Route::get('/accept-terms', [LegalController::class,'acceptPage'])->name('legal.accept');
        Route::post('/accept-terms', [LegalController::class,'accept'])->middleware('throttle:10,1,legal-accept-store:')->name('legal.accept.store');
    });
    Route::middleware(['auth',RequireVerifiedOwner::class,RequireLegalTerms::class])->group(function () use ($staffRoutes) {
        Route::get('/venues', [PlatformController::class, 'venues'])->name('venues');
        Route::get('/notifications',[NotificationController::class,'index'])->name('notifications.index');
        Route::get('/notifications/drawer',[NotificationController::class,'drawer'])->middleware('throttle:60,1,notifications-drawer:')->name('notifications.drawer');
        Route::get('/notifications/count',[NotificationController::class,'count'])->middleware('throttle:60,1,notifications-count:')->name('notifications.count');
        Route::post('/notifications/read-all',[NotificationController::class,'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{record}/read',[NotificationController::class,'read'])->whereNumber('record')->name('notifications.read');
        Route::patch('/notifications/preferences',[NotificationController::class,'preferences'])->name('notifications.preferences');
        Route::prefix('venues/{venueSlug}')->where(['venueSlug'=>'[a-z0-9]+(?:-[a-z0-9]+)*'])
            ->middleware([ResolveStaffVenue::class,\App\Http\Middleware\TrackVenueActivity::class])->group($staffRoutes);
        Route::middleware('can:platform-admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [PlatformController::class, 'admin'])->name('index');
            Route::get('/rewards', [\App\Http\Controllers\RewardAdminController::class,'index'])->name('rewards.index');
            Route::post('/rewards/testimonials/{record}', [\App\Http\Controllers\RewardAdminController::class,'review'])->whereNumber('record')->name('rewards.review');
            Route::post('/rewards/grants/{grant}/revoke', [\App\Http\Controllers\RewardAdminController::class,'revoke'])->whereNumber('grant')->name('rewards.revoke');
            Route::get('/email', [\App\Http\Controllers\EmailAdminController::class,'index'])->name('email.index');
            Route::patch('/email', [\App\Http\Controllers\EmailAdminController::class,'save'])->middleware('throttle:platform-email-save')->name('email.save');
            Route::post('/email/test', [\App\Http\Controllers\EmailAdminController::class,'test'])->middleware('throttle:platform-email-test')->name('email.test');
            Route::patch('/email/activation', [\App\Http\Controllers\EmailAdminController::class,'activation'])->middleware('throttle:platform-email-activation')->name('email.activation');
            Route::get('/legal', [LegalAdminController::class,'index'])->name('legal.index');
            Route::patch('/legal/{document}', [LegalAdminController::class,'save'])->whereIn('document',\App\Services\LegalPolicies::SLUGS)->name('legal.save');
            Route::post('/legal/publish', [LegalAdminController::class,'publish'])->name('legal.publish');
            Route::get('/privacy-requests', [PrivacyRequestController::class,'index'])->name('privacy.index');
            Route::post('/privacy-requests', [PrivacyRequestController::class,'store'])->name('privacy.store');
            Route::patch('/privacy-requests/{record}', [PrivacyRequestController::class,'update'])->whereNumber('record')->name('privacy.update');
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
        Route::post('/login', [StaffAuthController::class, 'store'])->middleware('throttle:10,1,login-store:')->name('login.store');
        });
        Route::middleware('auth')->group(function () use ($staffRoutes) {
            Route::post('/logout', [StaffAuthController::class, 'destroy'])->name('logout');
            Route::get('/accept-terms', [LegalController::class,'acceptPage'])->name('legal.accept');
            Route::post('/accept-terms', [LegalController::class,'accept'])->middleware('throttle:10,1,legal-accept-store:')->name('legal.accept.store');
            Route::middleware([RequireLegalTerms::class,ResolveStaffVenue::class.':legacy'])->group($staffRoutes);
        });
    });
});
