@props(['columns', 'config'])

@php
    use Filament\Support\Facades\FilamentAsset;
    use Leek\FilamentRightClick\Macros\RegisterMacros;

    $rightClickAttributes = RegisterMacros::getFlowforgeContextMenuAttributes($board);
@endphp

<div
    {{
        $attributes
            ->merge($rightClickAttributes, escape: false)
            ->class([
                'w-full h-full flex flex-col relative',
            ])
    }}
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('flowforge', package: 'relaticle/flowforge') }}"
    x-data="flowforge({
        state: {
            columns: @js($columns),
            titleField: '{{ $config['recordTitleAttribute'] }}',
            columnField: '{{ $config['columnIdentifierAttribute'] }}',
            cardLabel: '{{ $config['cardLabel'] }}',
            pluralCardLabel: '{{ $config['pluralCardLabel'] }}',
        }
    })"
>
    @unless($config['headerToolbar'] ?? false)
        @include('flowforge::components.filters')
    @endunless

    <div class="flex-1 overflow-hidden h-full">
        <div class="flex flex-row h-full overflow-x-auto overflow-y-hidden gap-x-5">
            @foreach($columns as $columnId => $column)
                @include('flowforge::livewire.column', [
                    'columnId' => $columnId,
                    'column' => $column,
                    'config' => $config,
                ])
            @endforeach
        </div>
    </div>

    @include('filament-actions::components.modals')
</div>
