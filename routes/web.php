<?php

use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use App\Models\Booking;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/', static function () {
    return view('welcome');
})->name('home');
Route::get('/privacy-policy', static function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('booking/{booking}/success', static function (Booking $booking, Request $request) {
    if ($request->filled('session_id')) {
        if ($request->session_id !== $booking->stripe_checkout_session_id) {
            abort(404);
        }
    } else {
        if (! Auth::check() || Auth::user()->email !== $booking->email) {
            abort(404);
        }
    }

    return view('booking.success', ['booking' => $booking]);
})->name('booking.success')->withTrashed();

Route::get('membership/{membership}/success', static function (Membership $membership, Request $request) {
    if ($request->filled('session_id')) {
        if ($request->session_id !== $membership->stripe_checkout_session_id) {
            abort(404);
        }
    } else {
        abort(404);
    }

    return view('membership.success', ['membership' => $membership]);
})->name('membership.success');

Route::livewire('membership/{membership}/manage', 'membership.membership-manage')
    ->name('membership.manage');

Route::view('check-in', 'check-in')->name('check-in');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', 'admin/bookings')->name('dashboard');

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

    Route::view('memberships', 'dashboard.membership-list')->name('memberships.index');
    Route::livewire('memberships/{membership}/edit', 'membership.membership-edit')->name('memberships.edit');

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
