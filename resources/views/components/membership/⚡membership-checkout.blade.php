<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Actions\CreatePendingMembership;
use App\Exceptions\InvalidMembershipSelectionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\BookingStartLimiter;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new class extends Component
{
    #[Locked]
    public ?int $membershipServiceId = null;

    /**
     * @var list<array{schedule_id: int, date: string}>
     */
    #[Locked]
    public array $sessions = [];

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public bool $isProcessing = false;

    /**
     * Whether a picked session was taken while the customer was paying.
     */
    public bool $selectionChanged = false;

    /**
     * Open the checkout for the membership and sessions picked in the timetable.
     *
     * The picks come from the browser, so the purchase action checks them all again.
     *
     * @param  array<int, mixed>  $sessions
     */
    #[On('open-membership-checkout')]
    public function open(int $membershipServiceId, array $sessions): void
    {
        $this->resetErrorBag();
        $this->reset('isProcessing', 'selectionChanged');
        unset($this->membershipService, $this->sessionRows);

        $this->membershipServiceId = $membershipServiceId;
        $this->sessions = collect($sessions)
            ->filter(fn ($session) => is_array($session) && isset($session['schedule_id'], $session['date']))
            ->map(fn (array $session) => ['schedule_id' => (int) $session['schedule_id'], 'date' => (string) $session['date']])
            ->values()
            ->all();

        Flux::modal('membership-checkout')->show();
    }

    #[Computed]
    public function membershipService(): ?Service
    {
        if ($this->membershipServiceId === null) {
            return null;
        }

        return Service::where('is_membership', true)->where('is_active', true)->find($this->membershipServiceId);
    }

    /**
     * The picked sessions in date order, ready to show.
     *
     * @return list<array{date: string, time: string, service: string}>
     */
    #[Computed]
    public function sessionRows(): array
    {
        $schedules = Schedule::with('service')
            ->whereIn('id', array_column($this->sessions, 'schedule_id'))
            ->get()
            ->keyBy('id');

        return collect($this->sessions)
            ->filter(fn (array $session) => $schedules->has($session['schedule_id']) && Carbon::canBeCreatedFromFormat($session['date'], 'Y-m-d'))
            ->map(function (array $session) use ($schedules) {
                $schedule = $schedules->get($session['schedule_id']);

                return [
                    'sort_key' => $session['date'].' '.$schedule->start_time,
                    'date' => Carbon::parse($session['date'])->format('d.m.'),
                    'time' => substr((string) $schedule->start_time, 0, 5),
                    'service' => $schedule->service->name,
                ];
            })
            ->sortBy('sort_key')
            ->map(fn (array $row) => collect($row)->except('sort_key')->all())
            ->values()
            ->all();
    }

    /**
     * First and last day of the membership: one month from the earliest session.
     */
    #[Computed]
    public function periodLabel(): string
    {
        $dates = collect($this->sessions)
            ->pluck('date')
            ->filter(fn (string $date) => Carbon::canBeCreatedFromFormat($date, 'Y-m-d'));

        if ($dates->isEmpty()) {
            return '';
        }

        $periodStart = Carbon::parse($dates->min());

        return $periodStart->format('d.m.Y').' – '.$periodStart->copy()->addMonth()->format('d.m.Y');
    }

    /**
     * Reserve the sessions and send the customer to Stripe to pay.
     */
    public function pay(): void
    {
        if ($this->isProcessing || $this->selectionChanged || $this->membershipService === null) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'name.required' => __('Vārds ir obligāts.'),
            'name.max' => __('Vārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'surname.required' => __('Uzvārds ir obligāts.'),
            'surname.max' => __('Uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'phone.required' => __('Tālrunis ir obligāts.'),
            'phone.max' => __('Tālrunis nedrīkst pārsniegt 50 rakstzīmes.'),
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('E-pastam jābūt derīgai e-pasta adresei.'),
            'email.max' => __('E-pasts nedrīkst pārsniegt 255 rakstzīmes.'),
        ]);

        $limiter = app(BookingStartLimiter::class);

        if ($limiter->tooManyStarts()) {
            $this->addError('membership', $limiter->tooManyStartsMessage());

            return;
        }

        $this->isProcessing = true;

        try {
            $membership = app(CreatePendingMembership::class)->execute($this->membershipServiceId, $this->sessions, [
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'email' => $this->email,
            ]);
        } catch (InvalidMembershipSelectionException) {
            $this->isProcessing = false;
            $this->addError('membership', __('Izvēlētās nodarbības neatbilst abonementam. Lūdzu, pārbaudiet izvēli.'));

            return;
        } catch (SlotUnavailableException) {
            $this->isProcessing = false;
            $this->selectionChanged = true;
            $this->dispatch('timetable-refresh');

            return;
        }

        try {
            $checkout = app(CreateMembershipCheckoutSession::class)->execute($membership);
        } catch (ApiErrorException) {
            // Without a payment page nobody can pay, so free the places straight away.
            $membership->delete();

            $this->isProcessing = false;
            $this->addError('membership', __('Maksājuma sistēma pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz.'));

            return;
        }

        // Only a start that holds places and reaches Stripe counts towards the limit.
        $limiter->hit();

        $this->redirect($checkout['url'], navigate: false);
    }
};
?>

<div>
    <flux:modal name="membership-checkout" :dismissible="false" class="w-[calc(100vw-2rem)] max-w-lg">
        @if($selectionChanged)
            <div class="space-y-6 p-6 md:p-8">
                <flux:heading size="lg">{{ __('Kāds no izvēlētajiem laikiem vairs nav pieejams.') }}</flux:heading>
                <flux:text>{{ __('Tas ir noņemts no jūsu izvēles. Lūdzu, izvēlieties citu nodarbību sarakstā.') }}</flux:text>
                <flux:modal.close>
                    <flux:button class="btn btn-sm btn-primary">{{ __('Aizvērt') }}</flux:button>
                </flux:modal.close>
            </div>
        @elseif($this->membershipService)
            <form wire:submit="pay" class="space-y-6 p-6 md:p-8">
                <flux:heading size="lg">{{ __('Abonementa iegāde') }}</flux:heading>

                {{-- MEMBERSHIP SUMMARY --}}
                <div>
                    <div class="flex justify-between gap-4 font-medium">
                        <span>{{ $this->membershipService->name }}</span>
                        <span class="shrink-0">{{ price_label($this->membershipService->price) }}</span>
                    </div>
                    <flux:text size="sm">{{ $this->periodLabel }}</flux:text>
                </div>

                {{-- SESSIONS (collapsed: the customer has just picked them) --}}
                <div x-data="{ open: false }">
                    <button type="button" class="flex items-center gap-1 text-sm font-medium" @click="open = ! open"
                            :aria-expanded="open">
                        <flux:icon.chevron-right variant="micro" class="transition-transform" ::class="open && 'rotate-90'"/>
                        {{ count($this->sessionRows) }} {{ count($this->sessionRows) === 1 ? __('nodarbība') : __('nodarbības') }}
                        <span class="font-normal text-zinc-500" x-text="open ? '{{ __('(slēpt)') }}' : '{{ __('(rādīt)') }}'"></span>
                    </button>
                    <ul x-show="open" x-collapse x-cloak class="mt-2 divide-y divide-zinc-100 text-sm">
                        @foreach($this->sessionRows as $row)
                            <li wire:key="checkout-session-{{ $loop->index }}" class="flex gap-3 py-1.5">
                                <span class="shrink-0 whitespace-nowrap tabular-nums text-zinc-500">{{ $row['date'] }} {{ $row['time'] }}</span>
                                <span>{{ $row['service'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- CUSTOMER DETAILS --}}
                <x-booking.customer-fields/>

                @error('membership')
                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                @enderror

                <div class="flex justify-between">
                    <flux:modal.close>
                        <flux:button class="btn btn-sm btn-tertiary">{{ __('Atcelt') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" class="btn btn-sm btn-primary"
                                 :disabled="$isProcessing">{{ __('Apmaksāt ar karti') }}</flux:button>
                </div>
            </form>
        @else
            <div class="space-y-6 p-6 md:p-8">
                <flux:heading size="lg">{{ __('Šis abonements vairs nav pieejams.') }}</flux:heading>
                <flux:text>{{ __('Lūdzu, izvēlieties citu abonementu.') }}</flux:text>
                <flux:modal.close>
                    <flux:button class="btn btn-sm btn-primary">{{ __('Aizvērt') }}</flux:button>
                </flux:modal.close>
            </div>
        @endif
    </flux:modal>
</div>
