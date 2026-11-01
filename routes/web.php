<?php

use App\Http\Controllers\LocaleController;
use App\Livewire\Game\Room;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/room/{room}', Room::class)->name('rooms.show');
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
