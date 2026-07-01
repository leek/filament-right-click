@props(['columns', 'config'])

@php
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
>
    @include('flowforge::index', [
        'columns' => $columns,
        'config' => $config,
        'board' => $board,
    ])
</div>
