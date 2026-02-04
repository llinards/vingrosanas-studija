<?php

use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::group([
    'prefix' => LaravelLocalization::setLocale(), 'middleware' => [
        'localeSessionRedirect',
        'localizationRedirect',
    ],
], static function () {
    Route::get('/', static function () {
        return view('welcome');
    })->name('home');

    Route::middleware(['auth'])->group(function () {
        Route::view('dashboard', 'dashboard.dashboard')->name('dashboard');
        Route::view('coaches', 'dashboard.coach-list')->name('coach-list');
        Route::livewire('coach/add', 'coach.coach-create')->name('coach-create');
        Route::livewire('coach/{coach}/edit', 'coach.coach-edit')->name('coach.edit');

        Route::redirect('settings', 'settings/profile');
        Route::livewire('settings/profile', Profile::class)->name('profile.edit');
        Route::livewire('settings/password', Password::class)->name('user-password.edit');
        Route::livewire('settings/two-factor', TwoFactor::class)
             ->middleware(
                 when(
                     Features::canManageTwoFactorAuthentication()
                     && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                     ['password.confirm'],
                     [],
                 ),
             )
             ->name('two-factor.show');
    });
});
