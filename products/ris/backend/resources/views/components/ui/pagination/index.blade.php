@props([
    'paginator' => null,
    'variant' => 'default',
    'options' => [10, 15, 20, 25, 30, 35, 40]
])



@php
    $lengthAwarePagination = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    if (!in_array($variant, ['full', 'default'])) {
        throw new RuntimeException('invalid variant name');
    }

    $path = match ($lengthAwarePagination) {
        true => 'ui.pagination.length-aware.variants.' . $variant,
        false => 'ui.pagination.simple.variants.' . $variant,
    };
@endphp


@if ($paginator->hasPages())
    <x-dynamic-component :component="$path" />
@else
    <div></div>
@endif
