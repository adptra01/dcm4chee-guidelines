@props([
    'paginator' => null,
    'loading' => null,
    'top' => null,
    'reorderable' => null,
    'loadOn' => null,
    'footer' => null 
])

@php
    $hasWireLoading = filled($attributes->whereStartsWith('wire:loading')->first());

    $loadTargets = is_array($loadOn)
        ? $loadOn 
        : array_filter(array_map('trim', explode(',', $loadOn ?? '')));

    $targets = [];

    if (in_array('sorting', $loadTargets)) {
        array_push($targets,'sortByColumn');
    }
    
    if (in_array('search', $loadTargets)) {
        array_push($targets,'searchQuery');
    }
    
    if (in_array('pagination', $loadTargets)) {
        array_push($targets, 'nextPage', 'gotoPage', 'previousPage', 'setPage','perPage');
    }

    $existingTarget = $attributes->get('wire:target');

    if ($existingTarget) {
        $existingTargets = array_map('trim', explode(',', $existingTarget));
        $targets = array_merge($targets, $existingTargets);
    }

    $wireTarget = !empty($targets) ? implode(',', array_unique($targets)) : null;

    $loadingAttributes = (new \Illuminate\View\ComponentAttributeBag())->merge(
        $hasWireLoading 
            ? [
                'wire:loading.attr' => 'data-loading',
                'wire:target' => $wireTarget,
            ]
            : [],
    );

    $classes = [
        '[:where(&)]:min-w-full flex -mx-(--table-padding) overflow-x-auto',
        'data-[loading]:opacity-50 transition-opacity',
    ];

    $tableClasses = [
        '[:where(&)]:min-w-full isolate table-fixed whitespace-nowrap text-neutral-800 dark:text-neutral-100 text-nowrap',
        'divide-y divide-neutral-800/5 dark:divide-white/5',
        $attributes->get('table:class'),
    ];
    
    // Build the pagination attrs bag
    $paginatorAttrs = new \Illuminate\View\ComponentAttributeBag();

    if ($paginationVariant = $attributes->get('pagination:variant')) {
        $paginatorAttrs = $paginatorAttrs->merge(['variant' => $paginationVariant]);
    }
    
    if ($paginationOptions = $attributes->get('pagination:options')) {
        $paginatorAttrs = $paginatorAttrs->merge(['options' => $paginationOptions]);
    }

    $ignoredAttrs = [
        'wire:loading',
        'wire:target',
        'pagination:variant',
        'pagination:options',
        'loadOn',
    ];
@endphp

<div class="relative w-full">
    <div {{ $attributes->except($ignoredAttrs)->class($classes)->merge($loadingAttributes->all()) }}>
        <table class="{{ Arr::toCssClasses($tableClasses) }}">
            {{ $slot }}
        </table>
    
        {{-- loading indicator --}}
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" {{ $loadingAttributes }}>
            <div class="hidden [[data-loading=true]>&]:inline-flex h-full w-full">
                @if ($loading instanceof \Illuminate\View\ComponentSlot)
                    <div {{ $loading->attributes }}>
                        {{ $loading }}
                    </div>
                @else
                    <x-ui.icon.loading class="dark:invert"/>
                @endif
            </div>
        </div>
    </div>

    @if ($footer)
        {{ $footer }}    
    @endif

    @if ($paginator)
        <x-ui.pagination 
            :$paginator 
            :attributes="$paginatorAttrs"
        />
    @endif
</div>