<?php

use App\Enums\Locale;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Game\Room;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/{locale}', Home::class)
    ->name('home.localized')
    ->whereIn('locale', array_map(fn (Locale $locale): string => $locale->value, Locale::alternates()));
Route::permanentRedirect('/'.Locale::default()->value, '/');
Route::livewire('/room/{room}', Room::class)->name('rooms.show');
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
