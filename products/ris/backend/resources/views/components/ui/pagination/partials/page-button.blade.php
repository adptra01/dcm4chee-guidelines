@props([
    'active' => false,
    'page' => null,
])

@if ($active)
    <div
        {{ $attributes->class('flex justify-center items-center text-xs size-8 px-2 rounded-[calc(var(--paginator-radius)-var(--paginator-padding))] font-medium dark:text-white text-neutral-900  dark:bg-white/5 bg-neutral-900/5') }}
    >
        {{ $page }}
    </div>
@else
    <button type="button"
        {{ $attributes->class('text-xs size-8 cursor-pointer px-2 rounded-[calc(var(--paginator-radius)-var(--paginator-padding))] font-medium dark:text-white/60 text-neutral-900/60 dark:hover:bg-white/5 hover:bg-neutral-900/5  dark:hover:text-white hover:text-neutral-900 transition-colors') }}
    >
        {{ $page }}
    </button>
@endif
