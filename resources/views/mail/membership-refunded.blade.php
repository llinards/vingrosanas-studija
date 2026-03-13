<x-mail::message>
# Abonementa atmaksa

Jūsu abonements ir atcelts un atmaksa tiks atgriezta.

**Abonementa informācija:**

- **Abonements:** {{ $membership->tierLabel() }}
- **Periods:** {{ $membership->period_start->format('d.m.Y') }} — {{ $membership->period_end->format('d.m.Y') }}

Atmaksa tiks atgriezta uz jūsu maksājuma karti dažu darba dienu laikā.

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
