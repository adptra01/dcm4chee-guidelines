@aware(['sortable', 'currentSortDir'])

@props(['isSorted'])

<button 
    type="button"
    {{
        $attributes->class([
            "flex group py-2 mr-3 ml-1 items-center gap-2 text-left",
            'cursor-pointer' => $sortable,
        ])
    }}
>
    <span>{{ $slot }}</span>
    
    @if ($isSorted)
        <x-ui.icon 
            name="arrow-long-{{ $currentSortDir === 'asc' ? 'up' : 'down' }}" 
            variant="micro" 
            class="transition-transform transition-opacity duration-200"
        />
    @else
        <x-ui.icon 
            name="arrows-up-down" 
            variant="micro"
            class="opacity-0 group-hover:opacity-100 transition-opacity transition-opacity duration-200"
        />
    @endif
</button>