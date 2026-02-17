<?php

use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('dashboard page can be rendered', function () {
    $this->get('/admin')
        ->assertSuccessful();
});

test('dashboard stats component can be rendered', function () {
    Livewire::test('dashboard.dashboard-stats')
        ->assertSuccessful();
});

test('todays bookings count is accurate', function () {
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today()->addDay()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->todaysBookingsCount)->toBe(2);
});

test('todays revenue only includes paid bookings', function () {
    $service = Service::factory()->create(['price' => 1500]);
    $schedule = Schedule::factory()->create(['service_id' => $service->id]);

    Booking::factory()->paid()->create([
        'booking_date' => today(),
        'schedule_id' => $schedule->id,
    ]);
    Booking::factory()->paid()->create([
        'booking_date' => today(),
        'schedule_id' => $schedule->id,
    ]);
    Booking::factory()->create([
        'booking_date' => today(),
        'schedule_id' => $schedule->id,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->todaysRevenue)->toBe(3000);
});

test('pending payments count excludes other statuses', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Paid]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Refunded]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->pendingPaymentsCount)->toBe(2);
});

test('this week bookings count is accurate', function () {
    Booking::factory()->create(['booking_date' => now()->startOfWeek()]);
    Booking::factory()->create(['booking_date' => now()->endOfWeek()]);
    Booking::factory()->create(['booking_date' => now()->subWeek()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->thisWeekBookings)->toBe(2);
});

test('last week bookings count is accurate', function () {
    Booking::factory()->create(['booking_date' => now()->subWeek()->startOfWeek()]);
    Booking::factory()->create(['booking_date' => now()->subWeek()->endOfWeek()]);
    Booking::factory()->create(['booking_date' => now()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->lastWeekBookings)->toBe(2);
});

test('weekly trend calculates positive change correctly', function () {
    Booking::factory()->count(3)->create(['booking_date' => now()->subWeek()->startOfWeek()]);
    Booking::factory()->count(6)->create(['booking_date' => now()->startOfWeek()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    $trend = $component->instance()->weeklyTrend;
    expect($trend['direction'])->toBe('up');
    expect($trend['change'])->toBe(100);
});

test('weekly trend calculates negative change correctly', function () {
    Booking::factory()->count(4)->create(['booking_date' => now()->subWeek()->startOfWeek()]);
    Booking::factory()->count(2)->create(['booking_date' => now()->startOfWeek()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    $trend = $component->instance()->weeklyTrend;
    expect($trend['direction'])->toBe('down');
    expect($trend['change'])->toBe(50);
});

test('weekly trend handles zero last week bookings', function () {
    Booking::factory()->count(3)->create(['booking_date' => now()->startOfWeek()]);

    $component = Livewire::test('dashboard.dashboard-stats');

    $trend = $component->instance()->weeklyTrend;
    expect($trend['direction'])->toBe('up');
    expect($trend['change'])->toBe(100);
});

test('most popular day returns correct day label', function () {
    $mondaySchedule = Schedule::factory()->create(['day_of_week' => DayOfWeek::Monday->value]);
    $fridaySchedule = Schedule::factory()->create(['day_of_week' => DayOfWeek::Friday->value]);

    Booking::factory()->count(5)->create(['schedule_id' => $mondaySchedule->id]);
    Booking::factory()->count(2)->create(['schedule_id' => $fridaySchedule->id]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->mostPopularDay)->toBe('Pirmdiena');
});

test('most popular day returns null when no bookings', function () {
    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->mostPopularDay)->toBeNull();
});

test('upcoming bookings count includes next 7 days', function () {
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today()->addDays(3)]);
    Booking::factory()->create(['booking_date' => today()->addDays(7)]);
    Booking::factory()->create(['booking_date' => today()->addDays(8)]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->upcomingBookingsCount)->toBe(3);
});

test('average capacity utilization is calculated correctly', function () {
    $schedule1 = Schedule::factory()->create(['max_capacity' => 10, 'is_active' => true]);
    $schedule2 = Schedule::factory()->create(['max_capacity' => 10, 'is_active' => true]);

    Booking::factory()->count(5)->create(['schedule_id' => $schedule1->id]);
    Booking::factory()->count(5)->create(['schedule_id' => $schedule2->id]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->averageCapacityUtilization)->toBe(50);
});

test('average capacity utilization excludes inactive schedules', function () {
    $activeSchedule = Schedule::factory()->create(['max_capacity' => 10, 'is_active' => true]);
    $inactiveSchedule = Schedule::factory()->create(['max_capacity' => 10, 'is_active' => false]);

    Booking::factory()->count(5)->create(['schedule_id' => $activeSchedule->id]);
    Booking::factory()->count(10)->create(['schedule_id' => $inactiveSchedule->id]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->averageCapacityUtilization)->toBe(50);
});

test('full classes count is accurate', function () {
    $fullSchedule = Schedule::factory()->create(['max_capacity' => 2, 'is_active' => true]);
    $partialSchedule = Schedule::factory()->create(['max_capacity' => 10, 'is_active' => true]);

    Booking::factory()->count(2)->create(['schedule_id' => $fullSchedule->id]);
    Booking::factory()->count(3)->create(['schedule_id' => $partialSchedule->id]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->fullClassesCount)->toBe(1);
});

test('available spots today is calculated correctly', function () {
    $todayDayOfWeek = today()->dayOfWeekIso;

    $todaySchedule = Schedule::factory()->create([
        'day_of_week' => $todayDayOfWeek,
        'max_capacity' => 10,
        'is_active' => true,
    ]);

    Booking::factory()->count(3)->create([
        'schedule_id' => $todaySchedule->id,
        'booking_date' => today(),
    ]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->availableSpotsToday)->toBe(7);
});

test('available spots today excludes other days schedules', function () {
    $todayDayOfWeek = today()->dayOfWeekIso;
    $tomorrowDayOfWeek = today()->addDay()->dayOfWeekIso;

    $todaySchedule = Schedule::factory()->create([
        'day_of_week' => $todayDayOfWeek,
        'max_capacity' => 10,
        'is_active' => true,
    ]);

    $tomorrowSchedule = Schedule::factory()->create([
        'day_of_week' => $tomorrowDayOfWeek,
        'max_capacity' => 10,
        'is_active' => true,
    ]);

    Booking::factory()->count(3)->create([
        'schedule_id' => $todaySchedule->id,
        'booking_date' => today(),
    ]);

    $component = Livewire::test('dashboard.dashboard-stats');

    expect($component->instance()->availableSpotsToday)->toBe(7);
});

test('dashboard displays stats in the UI', function () {
    Booking::factory()->create(['booking_date' => today()]);

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Šodienas rezervācijas')
        ->assertSee('Šodienas ieņēmumi')
        ->assertSee('Gaida apmaksu');
});
