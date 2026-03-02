<x-layouts.error :title="__('Servera kļūda')">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 text-center">
        <div class="mb-2 mx-auto w-28">
            <x-app-logo-icon/>
        </div>
        <h1 class="text-blue">500</h1>
        <h3 class="mt-4">{{ __('Servera kļūda') }}</h3>
        <flux:text class="mt-4 max-w-md">
            {{ __('Atvainojiet, radās neparedzēta kļūda. Lūdzu, mēģiniet vēlreiz vēlāk.') }}
        </flux:text>
        <a href="{{ route('home') }}" class="button large primary mt-8">
            {{ __('Uz sākumu') }}
        </a>
    </div>
    </x-layouts.main>
