<?php

use App\Models\UnavailableDate;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $coachId;

    public ?string $start_date = null;

    public ?string $end_date = null;

    /**
     * Initialize the component with the coach ID.
     */
    public function mount(int $coachId): void
    {
        $this->coachId = $coachId;
    }

    /**
     * Get all unavailable dates for this coach, ordered by start date.
     *
     * @return Collection<int, UnavailableDate>
     */
    #[Computed]
    public function unavailableDates(): Collection
    {
        return UnavailableDate::forCoach($this->coachId)
            ->whereDate('end_date', '>=', today())
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'start_date.required' => __('Sākuma datums ir obligāts.'),
            'start_date.date' => __('Sākuma datumam jābūt derīgam datumam.'),
            'start_date.after_or_equal' => __('Sākuma datums nevar būt pagātnē.'),
            'end_date.required' => __('Beigu datums ir obligāts.'),
            'end_date.date' => __('Beigu datumam jābūt derīgam datumam.'),
            'end_date.after_or_equal' => __('Beigu datumam jābūt vienādam vai vēlākam par sākuma datumu.'),
        ];
    }

    /**
     * Add a new unavailable date range for this coach.
     */
    public function add(): void
    {
        $this->validate();

        try {
            UnavailableDate::create([
                'coach_id' => $this->coachId,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ]);

            $this->reset('start_date', 'end_date');
            unset($this->unavailableDates);

            Flux::toast(
                text: __('Nepieejamais datums pievienots!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās pievienot datumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    /**
     * Delete an unavailable date entry.
     */
    public function destroy(UnavailableDate $unavailableDate): void
    {
        if ($unavailableDate->coach_id !== $this->coachId) {
            return;
        }

        try {
            $unavailableDate->delete();
            unset($this->unavailableDates);

            Flux::toast(
                text: __('Nepieejamais datums dzēsts!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst datumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    <flux:heading level="2" size="lg" class="mb-4">{{ __('Nepieejamie datumi') }}</flux:heading>
    <flux:separator class="mb-4"/>

    @if($this->unavailableDates->isEmpty())
        <flux:text class="py-4">{{ __('Nav norādītu nepieejamo datumu.') }}</flux:text>
    @else
        <flux:table class="mb-6">
            <flux:table.columns>
                <flux:table.column>{{ __('Sākuma datums') }}</flux:table.column>
                <flux:table.column>{{ __('Beigu datums') }}</flux:table.column>
                <flux:table.column>{{ __('Darbības') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->unavailableDates as $unavailableDate)
                    <flux:table.row wire:key="unavailable-{{ $unavailableDate->id }}">
                        <flux:table.cell>{{ $unavailableDate->start_date->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $unavailableDate->end_date->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:confirm="{{ __('Vai tiešām vēlies dzēst šo nepieejamo datumu?') }}"
                                wire:click="destroy({{ $unavailableDate->id }})"
                                variant="danger"
                                size="sm"
                            >
                                {{ __('Dzēst') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <form wire:submit="add" class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <flux:date-picker
                    wire:model="start_date"
                    min="today"
                    :label="__('Sākuma datums')"
                    :placeholder="__('Izvēlies datumu')"
                />
            </div>
            <div class="flex-1">
                <flux:date-picker
                    wire:model="end_date"
                    min="today"
                    :label="__('Beigu datums')"
                    :placeholder="__('Izvēlies datumu')"
                />
            </div>
        </div>

        <div>
            <flux:button type="submit" variant="primary" size="sm">
                {{ __('Pievienot') }}
            </flux:button>
        </div>
    </form>
</div>
