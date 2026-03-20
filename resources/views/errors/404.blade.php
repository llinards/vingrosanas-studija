<x-layouts.error :title="__('Lapa nav atrasta')">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 text-center">
        <div class="mb-2 mx-auto w-28">
            <x-app-logo-icon/>
        </div>
        <h1 class="text-blue">404</h1>
        <h3 class="mt-4">{{ __('Lapa nav atrasta') }}</h3>
        <flux:text class="mt-4 max-w-md">
            {{ __('Diemžēl meklētā lapa neeksistē vai ir pārvietota.') }}
        </flux:text>
        <a href="{{ route('home') }}" class="btn btn-sm btn-primary mt-8">
            {{ __('Uz sākumu') }}
        </a>
    </div>
</x-layouts.error>
