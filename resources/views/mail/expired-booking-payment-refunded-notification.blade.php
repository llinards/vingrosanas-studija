<x-mail::message>
# Automātiska atmaksa

Klienta maksājums tika saņemts pēc tam, kad neapmaksātā rezervācija jau bija dzēsta. Rezervācija netika izveidota, un maksājums ir automātiski atgriezts.

- **Klienta e-pasts:** {{ $customerEmail ?? 'nav zināms' }}
- **Summa:** {{ number_format($amountInCents / 100, 2) }} EUR
- **Stripe maksājums:** {{ $paymentIntent }}

Klients par to ir informēts pa e-pastu. Papildu darbības nav nepieciešamas.

{{ config('app.name') }}
</x-mail::message>
