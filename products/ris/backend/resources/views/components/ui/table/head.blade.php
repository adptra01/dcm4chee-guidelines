@aware(['variant' => 'default'])

@props([
    'sortable' => false,
    'column' => null,
    'currentSortBy' => '',
    'currentSortDir' => '',
    'variant' => 'default',
    'sticky' => false,
])

@php       
    $classes = [
        '[:where(&)]:first:pl-2 [:where(&)]:last:pr-2 [:where(&)]:py-1 [:where(&)]:px-4 my-1 right-align',
        'text-left text-sm font-medium w-fit',
        'text-neutral-800 dark:text-white',
        'sticky left-0' => $sticky
    ];
@endphp

<th {{ $attributes->class($classes) }}>
    @if ($sortable)
        <x-ui.table.sortable>
            {{ $slot }}
        </x-ui.table.sortable>
    @else
        <div class="py-2">
            {{ $slot }}
        </div>
    @endif
</th>