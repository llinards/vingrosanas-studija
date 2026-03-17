<x-mail::message>
# Jauns abonements

Tev ir jauns abonementa pirkums!

**Klienta informācija:**

- **Vārds:** {{ $membership->name }} {{ $membership->surname }}
- **Tālrunis:** {{ $membership->phone }}
- **E-pasts:** {{ $membership->email }}

**Abonementa informācija:**

- **Abonements:** {{ $membership->tierLabel() }}
- **Periods:** {{ $membership->period_start->format('d.m.Y') }} — {{ $membership->period_end->format('d.m.Y') }}
- **Nodarbību skaits:** {{ $membership->sessions_total }}

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
