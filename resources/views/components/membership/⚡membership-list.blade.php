<?php

use App\Enums\PaymentStatus;
use App\Models\Membership;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * Selected payment statuses for filtering.
     *
     * @var array<int, string>
     */
    #[Url]
    public array $paymentStatuses = [];

    /**
     * Whether to show only active memberships.
     */
    #[Url]
    public bool $activeOnly = false;

    /**
     * Whether to show only expired memberships.
     */
    #[Url]
    public bool $expiredOnly = false;

    /**
     * Search query for filtering memberships.
     */
    #[Url]
    public string $search = '';

    /**
     * Initialize the component with all payment statuses selected.
     */
    public function mount(): void
    {
        if (empty($this->paymentStatuses)) {
            $this->paymentStatuses = collect(PaymentStatus::cases())
                ->map(fn (PaymentStatus $status) => $status->value)
                ->all();
        }
    }

    /**
     * Reset pagination when any filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Get paginated memberships filtered by payment status and search.
     */
    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return Membership::query()
            ->with('service')
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->whereIn('payment_status', $this->paymentStatuses)
            ->when($this->activeOnly, fn ($query) => $query->active())
            ->when($this->expiredOnly, fn ($query) => $query->where('period_end', '<', today()))
            ->withCount(['bookings' => fn ($query) => $query->whereNotIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::Failed])])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    /**
     * Check if any memberships exist in the database.
     */
    #[Computed]
    public function hasAnyMemberships(): bool
    {
        return Membership::exists();
    }

    /**
     * Delete a membership from the database.
     */
    public function destroy(Membership $membership): void
    {
        try {
            $membership->delete();

            unset($this->memberships);

            Flux::toast(
                text: __('Abonements veiksmīgi dzēsts!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst abonementu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if(!$this->hasAnyMemberships)
        <div class="flex flex-col items-center">
            <flux:text class="text-center py-8">{{ __('Šobrīd nav neviena abonementa!') }}</flux:text>
        </div>
    @else
        <div class="mb-6 flex flex-col gap-4">
            <flux:input prefix-icon="magnifying-glass" type="search"
                        wire:model.live.debounce.300ms="search" placeholder="{{ __('Meklēt abonementus') }}"/>

            <div class="flex flex-wrap items-end gap-8">
                <flux:checkbox.group wire:model.live="paymentStatuses">
                    @foreach(PaymentStatus::cases() as $status)
                        <flux:checkbox label="{{ $status->label() }}" value="{{ $status->value }}"/>
                    @endforeach
                </flux:checkbox.group>

                <flux:checkbox.group>
                    <flux:checkbox label="{{ __('Tikai aktīvie') }}" wire:model.live="activeOnly"/>
                    <flux:checkbox label="{{ __('Tikai beigušies') }}" wire:model.live="expiredOnly"/>
                </flux:checkbox.group>
            </div>
        </div>

        @if($this->memberships->isEmpty())
            <flux:text class="text-center py-8">{{ __('Nav neviena abonementa.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Klients') }}</flux:table.column>
                    <flux:table.column>{{ __('Abonements') }}</flux:table.column>
                    <flux:table.column>{{ __('Periods') }}</flux:table.column>
                    <flux:table.column>{{ __('Nodarbības') }}</flux:table.column>
                    <flux:table.column>{{ __('Maksājums') }}</flux:table.column>
                    <flux:table.column>{{ __('Darbības') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->memberships as $membership)
                        <flux:table.row wire:key="membership-{{ $membership->id }}">
                            <flux:table.cell>
                                <div>{{ $membership->name }} {{ $membership->surname }}</div>
                                <div class="text-xs text-zinc-500">{{ $membership->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $membership->tierLabel() }}</flux:table.cell>
                            <flux:table.cell>{{ $membership->period_start->format('d.m.Y') }} — {{ $membership->period_end->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $membership->bookings_count }}/{{ $membership->sessions_total }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($membership->payment_status->value) {
                                    'paid' => 'green',
                                    'pending' => 'yellow',
                                    'failed' => 'red',
                                    'refunded' => 'zinc',
                                }">{{ $membership->payment_status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button href="{{ route('admin.memberships.edit', $membership) }}" variant="primary"
                                                 size="sm"
                                                 icon="pencil">
                                    </flux:button>
                                    <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst abonementu?') }}"
                                                 variant="danger"
                                                 size="sm"
                                                 icon="trash"
                                                 wire:click="destroy({{ $membership->id }})">
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->memberships->links() }}
            </div>
        @endif
    @endif
</div>
