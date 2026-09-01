@aware(['variant' => 'default','sortable' => false,'column' => null,'currentSortDir' => '','currentSortBy' => ''])

<x-ui.table.sortable.head-button
    :$isSorted 
    wire:click="sortByColumn('{{ $column }}')"
>
    {{ $slot }}
</x-ui.table.sortable.head-button>