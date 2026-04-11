@php
    use App\Filament\Resources\Orders\OrderResource;
    use App\Models\Order;

    $record = $column->getRecord();
    $activeTab = \Livewire\Livewire::current()?->activeTab ?? null;
    $createdAt = $record instanceof Order ? $record->created_at : null;
    $formatted = $createdAt ? $createdAt->format('d/m/Y H:i') : '—';
    $showEditLink = $activeTab !== 'delivered' && $activeTab !== 'trash';
    $showTrash = $activeTab !== 'delivered' && $record instanceof Order && ! $record->trashed();
    $recordKey = $record instanceof Order ? (string) $record->getKey() : '';
@endphp

<div class="fi-flex flex-col fi-gap-sm fi-align-start">
    <div class="fi-ta-text fi-wrapped">
        @if ($showEditLink && $record instanceof Order)
            <a
                href="{{ OrderResource::getUrl('edit', ['record' => $record]) }}"
                class="fi-link text-xs"
            >
                {{ $formatted }}
            </a>
        @else
            <span class="text-xs">{{ $formatted }}</span>
        @endif
    </div>

    @if ($showTrash && $recordKey !== '')
        <button
            type="button"
            wire:click.prevent.stop="mountTableAction('delete', @js($recordKey))"
            wire:loading.attr="disabled"
            x-on:click.stop
            class="fi-icon-btn fi-size-sm text-danger-600 dark:text-danger-400"
            title="{{ __('filament-actions::delete.single.label') }}"
        >
            <x-heroicon-m-trash class="h-5 w-5" />
        </button>
    @endif
</div>
