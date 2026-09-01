@aware(['paginator' => null ])

@if ($paginator->total() > 0)
    <div {{ $attributes->class("text-sm text-white/70 font-medium") }} role="status" aria-live="polite">
        <span class="text-neutral-700 dark:text-neutral-300">{{ $paginator->firstItem() }}</span>
        <span class="dark:text-neutral-400 text-neutral-600">–</span>
        <span class="text-neutral-700 dark:text-neutral-300">{{ $paginator->lastItem() }}</span>
        <span class="dark:text-neutral-400 text-neutral-600 mx-1">{{ __('of') }}</span>
        <span class="text-neutral-700 dark:text-neutral-300">{{ $paginator->total() }}</span>
        <span class="sr-only">{{ __('results') }}</span>
    </div>
@endif