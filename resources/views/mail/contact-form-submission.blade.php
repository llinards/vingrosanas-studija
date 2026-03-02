<x-mail::message>
# Jauna ziņa no kontaktformas

**Vārds:** {{ $name }} {{ $surname }}

**E-pasts:** {{ $email }}

**Ziņa:**

{{ $contactMessage }}

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
