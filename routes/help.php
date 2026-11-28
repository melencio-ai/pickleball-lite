<?php

use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

// Registered before legacy routes. No cookies, session, database or authentication.
foreach (array_values(array_unique(array_filter([config('pickle.help_host'),config('pickle.help_preview_host')]))) as $index=>$host) {
    Route::domain($host)->name($index===0?'help.':'help.preview.')->withoutMiddleware('web')->group(function () {
        Route::get('/', [HelpController::class,'index'])->name('index');
        Route::get('/search', [HelpController::class,'search'])->name('search');
        Route::get('/sitemap.xml', [HelpController::class,'sitemap'])->name('sitemap');
        Route::get('/robots.txt', [HelpController::class,'robots'])->name('robots');
        Route::get('/category/{category}', [HelpController::class,'category'])->where('category','[a-z0-9-]+')->name('category');
        Route::get('/articles/{category}/{slug}', [HelpController::class,'article'])->where(['category'=>'[a-z0-9-]+','slug'=>'[a-z0-9-]+'])->name('article');
        Route::any('/{missing}', [HelpController::class,'missing'])->where('missing','.*')->name('missing');
    });
}
