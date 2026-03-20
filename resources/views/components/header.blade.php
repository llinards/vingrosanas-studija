<header class="h-screen relative bg-cover bg-position-[35%_center] md:bg-center"
    style="background-image: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), url('{{ asset(site('hero', 'background_image', 'images/header_image.jpg')) }}');">

    <div class="container mx-auto px-4 pt-6 h-full flex flex-col relative">
        {{-- HEADING AND CTA --}}
        <div class="mt-auto pb-12 md:pb-36 md:max-w-1/5 space-y-4 md:space-y-6 flex flex-col items-start">
            <flux:heading level="1">{{ site('hero', 'heading', __('VINGROŠANAS STUDIJA veselīgam dzīvesveidam')) }}</flux:heading>
            <flux:modal.trigger name="booking-modal">
                <flux:button class="btn btn-lg btn-primary">{{ site('hero', 'cta_text', __('Pieteikties')) }}</flux:button>
            </flux:modal.trigger>
        </div>

        {{-- CONTACT/SOCIAL MEDIA ICONS --}}
        <div class="absolute right-4 top-1/2 -translate-y-1/2 z-10 flex flex-col space-y-6">
            <a href="tel:{{ site('contact', 'phone', '+37126620757') }}">
                <flux:icon.phone />
            </a>
            <a href="mailto:{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}">
                <flux:icon.envelope />
            </a>
            <a href="{{ site('contact', 'instagram_url', 'https://www.instagram.com/vingrosanas.studija') }}" target="_blank" rel="noopener noreferrer">
                <flux:icon.instagram />
            </a>
            <a href="{{ site('contact', 'facebook_url', 'https://www.facebook.com/vs.sigulda') }}" target="_blank" rel="noopener noreferrer">
                <flux:icon.facebook />
            </a>
        </div>
    </div>


</header>