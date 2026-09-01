@aware(['paginator' => null])

<div 
    {{ $attributes->class('text-sm font-medium') }}
    role="status" 
    aria-live="polite"
>
    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Page') }}</span>
    <span class="text-neutral-700 dark:text-neutral-300 mx-1">{{ $paginator->currentPage() }}</span>
    <span class="text-neutral-600 dark:text-neutral-400">{{ __('of') }}</span>
    <span class="text-neutral-700 dark:text-neutral-300 ml-1">{{ $paginator->lastPage() }}</span>
</div>