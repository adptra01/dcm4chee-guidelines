@aware(['variant'=>'default','sortable' => false,'column' => null,'currentSortDir' => '','currentSortBy' => ''])


<x-ui.dropdown 
    position="bottom-start"
>
    <x-slot:button class="justify-center">
        <x-ui.table.sortable.head-button
            :$isSorted  
            class=" rounded-box [[data-open]>&]:outline dark:outline-white/20 outline-neutral-900/20 dark:ring-white/15 ring-neutral-900/15 [[data-open]>&]:ring-2 [[data-open]>&]:bg-white/5 [[data-open]>&>[data-slot=icon]]:opacity-100 my-1 hover:bg-white/5 dark:ring-white/15 ring-neutral-900/15 px-4"
        >
            {{ $slot }}
        </x-ui.table.sortable.head-button>
    </x-slot:button>
    
        <x-slot:menu position="bottom-start">
        <x-ui.dropdown.item 
            wire:click="sortByColumn('{{ $column }}', 'asc')" 
            icon="arrow-long-up"
            :active="$isSorted && $currentSortDir === 'asc'"
        >
            Sort Ascending
        </x-ui.dropdown.item>
        
        <x-ui.dropdown.item 
            wire:click="sortByColumn('{{ $column }}', 'desc')"
            icon="arrow-long-down"
            :active="$isSorted && $currentSortDir === 'desc'"
        >
            Sort Descending
        </x-ui.dropdown.item>
        
        @if ($isSorted)
            <x-ui.dropdown.separator />
            <x-ui.dropdown.item 
                wire:click="sortByColumn('{{ $column }}', '')"
                icon="x-mark"
            >
                Clear Sort
            </x-ui.dropdown.item>
        @endif
    </x-slot:menu>
</x-ui.dropdown>