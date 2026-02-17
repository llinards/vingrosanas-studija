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
    Route::get('/privacy-policy', static function () {
        return view('privacy-policy');
    })->name('privacy-policy');

    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
        Route::view('/', 'dashboard.dashboard')->name('dashboard');

        Route::view('coaches', 'dashboard.coach-list')->name('coaches.index');
        Route::livewire('coaches/create', 'coach.coach-create')->name('coaches.create');
        Route::livewire('coaches/{coach}/edit', 'coach.coach-edit')->name('coaches.edit');

        Route::view('services', 'dashboard.service-list')->name('services.index');
        Route::livewire('services/create', 'service.service-create')->name('services.create');
        Route::livewire('services/{service}/edit', 'service.service-edit')->name('services.edit');

        Route::view('schedules', 'dashboard.schedule-list')->name('schedules.index');
        Route::livewire('schedules/create', 'schedule.schedule-create')->name('schedules.create');
        Route::livewire('schedules/{schedule}/edit', 'schedule.schedule-edit')->name('schedules.edit');

        Route::view('bookings', 'dashboard.booking-list')->name('bookings.index');
        Route::livewire('bookings/create', 'booking.booking-create')->name('bookings.create');
        Route::livewire('bookings/{booking}/edit', 'booking.booking-edit')->name('bookings.edit');

        Route::redirect('settings', 'settings/profile');
        Route::livewire('settings/profile', Profile::class)->name('settings.profile');
        Route::livewire('settings/password', Password::class)->name('settings.password');
        Route::livewire('settings/two-factor', TwoFactor::class)
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('settings.two-factor');
    });
});
