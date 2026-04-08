<?php

use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'active';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $serviceId = '';

    #[Url]
    public string $search = '';

    /**
     * Reset pagination when any filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function membershipServices(): Collection
    {
        return Service::where('is_membership', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get paginated memberships filtered by all active filters.
     */
    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return Membership::query()
                         ->with('service')
                         ->when($this->search, fn($query) => $query->search($this->search))
                         ->when($this->paymentStatus, fn($query) => $query->where('payment_status', $this->paymentStatus))
                         ->when($this->serviceId, fn($query) => $query->where('service_id', $this->serviceId))
                         ->when($this->status === 'active', fn($query) => $query->where('period_end', '>=', today()))
                         ->when($this->status === 'expired', fn($query) => $query->where('period_end', '<', today()))
                         ->withCount([
                             'bookings' => fn($query) => $query->whereNotIn('payment_status',
                                 [PaymentStatus::Refunded, PaymentStatus::Failed])
                         ])
                         ->orderBy('period_start', 'asc')
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

            <div class="flex flex-wrap items-end gap-4">
                <flux:select wire:model.live="status" :label="__('Statuss')">
                    <flux:select.option value="active">{{ __('Aktīvie') }}</flux:select.option>
                    <flux:select.option value="expired">{{ __('Beigušies') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('Visi') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="paymentStatus" :label="__('Maksājuma statuss')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach(PaymentStatus::cases() as $paymentStatusCase)
                        <flux:select.option :value="$paymentStatusCase->value">{{ $paymentStatusCase->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="serviceId" :label="__('Abonements')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach($this->membershipServices as $service)
                        <flux:select.option :value="$service->id">{{ $service->name }}</flux:select.option>
                    @endforeach
                </flux:select>
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
                            <flux:table.cell>{{ $membership->period_start->format('d.m.Y') }}
                                — {{ $membership->period_end->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $membership->bookings_count }}
                                /{{ $membership->sessions_total }}</flux:table.cell>
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
                                    <flux:button href="{{ route('admin.memberships.edit', $membership) }}"
                                                 variant="primary"
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
