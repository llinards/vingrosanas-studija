<x-mail::message>
# Abonementa apstiprinājums

Paldies par abonementa iegādi!

**Abonementa informācija:**

- **Abonements:** {{ $membership->tierLabel() }}
- **Periods:** {{ $membership->period_start->format('d.m.Y') }} — {{ $membership->period_end->format('d.m.Y') }}
- **Cena:** {{ number_format($membership->price / 100, 2) }} EUR

**Rezervētās nodarbības:**

@foreach($membership->bookings as $booking)
- {{ $booking->booking_date->format('d.m.Y') }} {{ substr($booking->schedule->start_time, 0, 5) }} — {{ $booking->schedule->service->name }}
@endforeach

Jūs varat pārbookot nodarbības savā abonementa pārvaldības lapā.

<x-mail::button :url="route('membership.manage', ['membership' => $membership, 'session_id' => $membership->stripe_checkout_session_id])">
Pārvaldīt abonementu
</x-mail::button>

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
