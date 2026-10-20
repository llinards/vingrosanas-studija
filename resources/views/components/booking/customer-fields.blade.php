{{-- Contact details asked in both public checkouts (single class and membership). --}}
<div class="grid gap-4 sm:grid-cols-2">
    <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadiet vārdu')"/>
    <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadiet uzvārdu')"/>
    <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadiet tālruni')"/>
    <flux:input wire:model="email" type="email" :label="__('E-pasts')" :placeholder="__('Ievadiet e-pastu')"/>
</div>
