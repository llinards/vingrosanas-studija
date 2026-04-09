<x-mail::message>
# Atgādinājums par abonementa maksājumu

Jūsu abonements vēl nav apmaksāts. Lūdzu, pabeidziet maksājumu tuvāko 10 minūšu laikā, lai saglabātu savu abonementu.

**Abonementa informācija:**

- **Abonements:** {{ $membership->tierLabel() }}
- **Periods:** {{ $membership->period_start->format('d.m.Y') }} — {{ $membership->period_end->format('d.m.Y') }}
- **Cena:** {{ number_format($membership->price / 100, 2) }} EUR

**Rezervētās nodarbības:**

@foreach($membership->bookings as $booking)
- {{ $booking->booking_date->format('d.m.Y') }} {{ substr($booking->schedule->start_time, 0, 5) }} — {{ $booking->schedule->service->name }}
@endforeach

**Ja maksājums netiks veikts, abonements un visas rezervācijas tiks automātiski dzēstas.**

<x-mail::button :url="$checkoutUrl">
Apmaksāt tagad
</x-mail::button>

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
