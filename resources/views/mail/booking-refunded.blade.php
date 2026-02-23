<x-mail::message>
# Rezervācijas atcelšana

Jūsu rezervācija ir atcelta.

**Rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}
- **Cena:** {{ number_format($booking->schedule->service->price / 100, 2) }} EUR

Atmaksa tiks ieskaitīta Jūsu kontā dažu darba dienu laikā.

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
