<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item :href="route('admin.content.contact')" wire:navigate>{{ __('Kontaktinformācija') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.hero')" wire:navigate>{{ __('Sākumlapa') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.quote')" wire:navigate>{{ __('Citāts') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.about')" wire:navigate>{{ __('Par mums') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.new-premises')" wire:navigate>{{ __('Jaunās telpas') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.media')" wire:navigate>{{ __('Mediji') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.content.seo')" wire:navigate>{{ __('SEO') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden"/>

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading level="3">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
