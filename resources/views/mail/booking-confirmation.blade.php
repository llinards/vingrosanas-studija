<x-mail::message>
# Rezervācijas apstiprinājums

Paldies par rezervāciju!

**Rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}
- **Cena:** {{ number_format($booking->schedule->service->price / 100, 2) }} EUR

Jūs varat atcelt rezervāciju ne vēlāk kā 24 stundas pirms nodarbības sākuma.

<x-mail::button :url="route('booking.success', ['booking' => $booking, 'session_id' => $booking->stripe_checkout_session_id])">
Rezervācijas informācija
</x-mail::button>

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
