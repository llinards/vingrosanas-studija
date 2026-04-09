<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:send-reminders')->everyMinute();
Schedule::command('bookings:expire-pending')->everyFiveMinutes();
Schedule::command('memberships:expire-pending')->everyFiveMinutes();
