<?php

use App\Actions\RefundBooking;
use App\Enums\PaymentStatus;
use App\Mail\BookingRefunded;
use App\Mail\BookingRefundedCoach;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Facades\Mail;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

beforeEach(function () {
    Mail::fake();

    $fakeClient = new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            return ['{"id":"re_test_coach","object":"refund"}', 200, []];
        }
    };

    ApiRequestor::setHttpClient($fakeClient);
});

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

function makeRefundableBooking(bool $notifyToggle, ?string $coachEmail): Booking
{
    $coach = Coach::factory()->create(['email' => $coachEmail]);

    $service = Service::factory()->create([
        'coach_id' => $coach->id,
        'notify_coach_on_cancellation' => $notifyToggle,
    ]);

    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'start_time' => '10:00',
    ]);

    return Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_coach_123',
    ]);
}

test('coach is notified when toggle on, coach has email and notifyCoach true', function () {
    $booking = makeRefundableBooking(notifyToggle: true, coachEmail: 'coach@example.com');

    app(RefundBooking::class)->execute($booking, notifyCoach: true);

    Mail::assertQueued(BookingRefundedCoach::class, function ($mail) {
        return $mail->hasTo('coach@example.com');
    });
    Mail::assertQueued(BookingRefunded::class, fn ($mail) => $mail->hasTo($booking->email));
});

test('coach is not notified when service toggle is off', function () {
    $booking = makeRefundableBooking(notifyToggle: false, coachEmail: 'coach@example.com');

    app(RefundBooking::class)->execute($booking, notifyCoach: true);

    Mail::assertNotQueued(BookingRefundedCoach::class);
});

test('coach is not notified when coach has no email', function () {
    $booking = makeRefundableBooking(notifyToggle: true, coachEmail: null);

    app(RefundBooking::class)->execute($booking, notifyCoach: true);

    Mail::assertNotQueued(BookingRefundedCoach::class);
});

test('coach is not notified when notifyCoach flag is false (admin/webhook path)', function () {
    $booking = makeRefundableBooking(notifyToggle: true, coachEmail: 'coach@example.com');

    app(RefundBooking::class)->execute($booking);

    Mail::assertNotQueued(BookingRefundedCoach::class);
});

test('booking refunded coach email contains correct data', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Refunded,
        'refund_reference' => 're_test_123',
        'refunded_at' => now(),
    ]);
    $booking->load('schedule.service.coach');

    $mailable = new BookingRefundedCoach($booking);

    $mailable->assertSeeInHtml($booking->schedule->service->name);
    $mailable->assertSeeInHtml($booking->name);
    $mailable->assertSeeInHtml($booking->booking_date->format('d.m.Y'));
});
