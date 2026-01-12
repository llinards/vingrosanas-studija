<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="container mx-auto">
    <flux:main>
        {{$slot}}
    </flux:main>
    @fluxScripts
</body>

</html>