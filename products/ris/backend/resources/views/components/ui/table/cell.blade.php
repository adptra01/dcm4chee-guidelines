@props([
    'align' => 'start',
    'variant' => null,
    'sticky' => false,
])

@php
    $classes = [
        '[:where(&)]:py-3 [:where(&)]:px-4 first:pl-2 last:pr-2 text-sm text-neutral-500 dark:text-neutral-300',
        'sticky left-0' => $sticky
    ];
@endphp

<td {{ $attributes->class($classes) }}>
    {{ $slot }}
</td>
