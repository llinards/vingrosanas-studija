<x-layouts.main :title="__('Sākums')">
    <flux:heading level="1">Hello World. Flux UI rulzzzz....</flux:heading>


    <flux:accordion>
        <flux:accordion.item>
            <flux:accordion.heading>What's your refund policy?</flux:accordion.heading>

            <flux:accordion.content>
                If you are not satisfied with your purchase, we offer a 30-day money-back guarantee. Please contact our
                support team for assistance.
            </flux:accordion.content>
        </flux:accordion.item>

        <flux:accordion.item>
            <flux:accordion.heading>Do you offer any discounts for bulk purchases?</flux:accordion.heading>

            <flux:accordion.content>
                Yes, we offer special discounts for bulk orders. Please reach out to our sales team with your
                requirements.
            </flux:accordion.content>
        </flux:accordion.item>

        <flux:accordion.item>
            <flux:accordion.heading>How do I track my order?</flux:accordion.heading>

            <flux:accordion.content>
                Once your order is shipped, you will receive an email with a tracking number. Use this number to track
                your
                order on our website.
            </flux:accordion.content>
        </flux:accordion.item>
    </flux:accordion>


    <flux:heading>Text component</flux:heading>
    <flux:text class="mt-2">This is the standard text component for body copy and general content throughout your
        application.</flux:text>

    <flux:button class="btn-primary">Pieteikties</flux:button>

    <flux:button class="btn-secondary">Pieteikties</flux:button>
</x-layouts.main>