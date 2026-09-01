@props([
    'border' => true
])

@php
    $classes = [
        'p-(--table-padding) [--table-padding:--spacing(2)] sm:[--table-padding:--spacing(3)] md:[--table-padding:--spacing(4)]',
        'rounded-box space-y-(--table-padding)',
        'border dark:border-white/10 border-neutral-900/10' => $border,
    ];
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
