@aware(['reorderable' => false])

@props([
    'key' => null,
    'checkboxId' => null,
    'order' => null
])

<tr 
    @if ($key) 
        wire:key="table-{{ $key }}" 
    @endif

    @if ($reorderable)
        x-sort:item="{{ $order }}"
    @endif

    {{ $attributes }}
>
    @if ($reorderable)
        <x-ui.table.cell
            class="px-2 w-fit"
        >
            <x-ui.button variant="soft" x-sort:handle size="sm" class="opacity-50 hover:bg-white/5 roundex-box" icon="equals"/>
        </x-ui.table.cell>  
    @endif
    
    @if ($checkboxId)
        <x-ui.table.cell
            class="px-0"
        >
            <x-ui.table.checkbox
                wire:model="selectedIds" 
                :$checkboxId 
                class="mx-2"
                
            />
        </x-ui.table.cell>  
    @endif
    {{ $slot }}
</tr>
