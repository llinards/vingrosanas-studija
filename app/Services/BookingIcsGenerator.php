<?php

namespace App\Services;

use App\Models\Booking;

class BookingIcsGenerator
{
    private const EVENT_DURATION_MINUTES = 60;

    private const REMINDER_MINUTES_BEFORE = 15;

    public function generate(Booking $booking): string
    {
        $start = $booking->getServiceDateTime()->utc();
        $end = $start->copy()->addMinutes(self::EVENT_DURATION_MINUTES);
        $stamp = now()->utc();

        $service = $booking->schedule->service;
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $customerName = trim("{$booking->name} {$booking->surname}");

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Vingrošanas Studija//Booking//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:booking-{$booking->id}@{$domain}",
            'DTSTAMP:'.$stamp->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape("{$service->name} — {$customerName}"),
            'DESCRIPTION:'.$this->escape("{$customerName} ({$booking->email}, {$booking->phone})"),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$this->escape("{$service->name} — {$customerName}"),
            'TRIGGER:-PT'.self::REMINDER_MINUTES_BEFORE.'M',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }
}
