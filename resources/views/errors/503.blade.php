<x-layouts.error :title="__('Apkope')">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 text-center">
        <div class="mb-2 mx-auto w-28">
            <x-app-logo-icon/>
        </div>
        <h1 class="text-blue">503</h1>
        <h3 class="mt-4">{{ __('Notiek uzlabošanas darbi') }}</h3>
        <flux:text class="mt-4 max-w-md">
            {{ __('Vietne šobrīd tiek uzlabota. Lūdzu, atgriezieties pēc brīža.') }}
        </flux:text>
    </div>
    </x-layouts.main>
