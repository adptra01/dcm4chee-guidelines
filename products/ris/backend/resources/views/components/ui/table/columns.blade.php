@aware(['reorderable' => false])

@props([
    'withCheckAll' => false,
])

<tr {{ $attributes }}>
    @if ($reorderable)
        <x-ui.table.head/>
    @endif

    @if ($withCheckAll)
        <x-ui.table.head class="px-0">
            <x-ui.table.check-all class="px-2"/>
        </x-ui.table.head>
    @endif

    {{ $slot }}
</tr>
