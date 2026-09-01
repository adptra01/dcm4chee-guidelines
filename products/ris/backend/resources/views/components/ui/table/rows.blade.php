@aware(['reorderable' => false])

@php
    $classes = [
        'divide-y divide-neutral-800/5 dark:divide-white/5',
        '[&_.dragged-item]:dark:bg-white/[2%] [&_.dragged-item]:bg-neutral-900/[2%]'
    ];
@endphp

<tbody
    @if($reorderable)
        x-sort="$wire.handleReordering"
        x-sort:config="{ 
            dragClass: 'dragged-item',
            forceFallback: true,
        }"
    @endif

    {{ $attributes->class($classes) }} 
>
    {{ $slot }}
</tbody>