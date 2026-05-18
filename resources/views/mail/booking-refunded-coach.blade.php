<x-mail::message>
# Rezervācija atcelta

Klients ir atcēlis rezervāciju un saņēmis atmaksu.

**Klienta informācija:**

- **Vārds:** {{ $booking->name }} {{ $booking->surname }}
- **Tālrunis:** {{ $booking->phone }}
- **E-pasts:** {{ $booking->email }}

**Atceltās rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
