<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="bg-body">
    <flux:main class="p-0!">
        @include('partials.nav')
        {{$slot}}
        @include('partials.footer')
    </flux:main>

    {{-- BOOKING MODAL --}}
    <livewire:booking-modal />
    @fluxScripts
</body>

</html>