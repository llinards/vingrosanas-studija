<?php

use App\Enums\PaymentStatus;
use App\Models\Membership;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /**
     * The page asks every five seconds, so this is two minutes of waiting.
     */
    private const MAX_CHECKS = 24;

    #[Locked]
    public int $membershipId;

    #[Locked]
    public string $sessionId;

    public int $checks = 0;

    public bool $hasStoppedChecking = false;

    public function mount(Membership $membership, string $sessionId): void
    {
        $this->membershipId = $membership->id;
        $this->sessionId = $sessionId;
    }

    /**
     * Look whether the payment has been confirmed and show the outcome once it has.
     */
    public function checkPayment(): void
    {
        $this->checks++;

        $membership = Membership::find($this->membershipId);

        if ($membership === null) {
            $this->hasStoppedChecking = true;

            return;
        }

        if ($membership->payment_status !== PaymentStatus::Pending) {
            $this->redirect(route('membership.success', [
                'membership' => $membership->id,
                'session_id' => $this->sessionId,
            ]));

            return;
        }

        if ($this->checks >= self::MAX_CHECKS) {
            $this->hasStoppedChecking = true;
        }
    }
};
?>

<div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4"
     @if(! $hasStoppedChecking) wire:poll.5s="checkPayment" @endif>
    <flux:icon.clock class="size-12 text-blue mx-auto mb-4"/>

    @if($hasStoppedChecking)
        <flux:heading level="3" class="text-center">{{ __('Apstiprinājums aizkavējies.') }}</flux:heading>
        <flux:text class="text-center mt-2">
            {{ __('Ja tuvākajā laikā nesaņemat apstiprinājuma e-pastu, lūdzu, sazinieties ar mums:') }}
            <a href="mailto:{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}"
               class="underline">{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}</a>
        </flux:text>
    @else
        <flux:heading level="3" class="text-center">{{ __('Maksājums tiek apstrādāts') }}</flux:heading>
        <flux:text class="text-center mt-2">
            {{ __('Lūdzu, uzgaidiet — šī lapa atjaunosies pati, tiklīdz maksājums būs apstiprināts.') }}
        </flux:text>
    @endif
</div>
