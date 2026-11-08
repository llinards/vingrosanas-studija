<x-mail::message>
# Rezervācijas apstiprinājums

Paldies par rezervāciju!

**Rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}
@if($booking->chargedPriceInCents() === 0)
- **Cena:** Bez maksas
@else
- **Cena:** {{ number_format($booking->chargedPriceInCents() / 100, 2) }} EUR
@endif

@if($booking->payment_reference)
Jūs varat atcelt rezervāciju ne vēlāk kā 24 stundas pirms nodarbības sākuma.
@else
Lai atceltu rezervāciju, lūdzu, sazinieties ar mums.
@endif

@if($booking->stripe_checkout_session_id)
<x-mail::button :url="route('booking.success', ['booking' => $booking, 'session_id' => $booking->stripe_checkout_session_id])">
Rezervācijas informācija
</x-mail::button>
@endif

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
