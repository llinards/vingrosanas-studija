<x-mail::message>
# Atgādinājums par maksājumu

Jūsu rezervācija vēl nav apmaksāta. Lūdzu, pabeidziet maksājumu tuvāko 10 minūšu laikā, lai saglabātu savu vietu.

**Rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}

**Ja maksājums netiks veikts, rezervācija tiks automātiski dzēsta.**

<x-mail::button :url="$checkoutUrl">
Apmaksāt tagad
</x-mail::button>

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
