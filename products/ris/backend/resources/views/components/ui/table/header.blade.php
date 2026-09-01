@aware(['reorderable' => false])

@props([
    'sticky' => false
])
    
<thead {{ $attributes->class(['sticky top-0 z-10' => $sticky]) }}>
    {{ $slot }}
</thead>
