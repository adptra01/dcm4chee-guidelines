@aware(['variant' => 'default', 'sortable' => false, 'column' => null, 'currentSortDir' => '', 'currentSortBy' => ''])

@php
    $isSorted = $sortable && $column === $currentSortBy;

    if (!in_array($variant, ['default', 'dropdown'])) {
        throw new RuntimeException('invalid variant name');
    }

    $path = "ui.table.sortable.variant.{$variant}";
@endphp

<x-dynamic-component 
    :component="$path"
    :$isSorted
>
    {{ $slot }}
</x-dynamic-component>
