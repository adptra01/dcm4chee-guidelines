@aware(['paginator', 'variant'])

<nav
    {{ $attributes->class('flex items-center justify-end pt-3 mt-2') }}
    aria-label="{{ __('Pagination Navigation') }}"
>
    @if ($paginator->hasPages())
        <div 
            @class([
                "flex items-center gap-0.5 [--paginator-radius:var(--radius-box)] [--paginator-padding:--spacing(.75)] rounded-(--paginator-radius) p-(--paginator-padding)",
                // bg's and borders
                " dark:bg-white/5 bg-neutral-900/5 border dark:border-white/10 border-neutral-900/10",
                // buttons's style... 
                "[&_button]:flex [&_button]:justify-center [&_button]:items-center [&_button]:size-8 [&_button]:rounded-[calc(var(--paginator-radius)-var(--paginator-padding))] [&_button]:text-white/60 [&_button]:transition-colors [&_button:not([data-extreme])]:cursor-pointer dark:[&_button:not([data-extreme])]:hover:bg-white/10 [&_button:not([data-extreme])]:hover:bg-neutral-900/10  [&_button:not([data-extreme])]:hover:text-white [&_button:has([data-extreme])]:pointer-events-none"
            ])
        >
            @if(method_exists($paginator, 'getCursorName'))
                <button 
                    type="button"
                    aria-label="{{ __('pagination.previous') }}"
                    @if(!$paginator->onFirstPage())
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->previousCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                    @else
                        data-extreme
                        aria-disabled="true"
                        disabled
                    @endif
                >
                    <x-ui.icon name="chevron-left" variant="micro" />
                </button>
            @else
                <button 
                    type="button"
                    aria-label="{{ __('pagination.previous') }}"
                    @if (!$paginator->onFirstPage())
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    @else
                        data-extreme
                        aria-disabled="true"
                        disabled
                    @endif
                >
                    <x-ui.icon name="chevron-left" variant="micro" />
                </button>
            @endif

            @if(method_exists($paginator, 'getCursorName'))
                <button 
                    type="button"
                    aria-label="{{ __('pagination.next') }}"
                    @if (!$paginator->onLastPage())
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->nextCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                    @else
                        data-extreme
                        aria-disabled="true"
                        disabled
                    @endif
                >
                    <x-ui.icon name="chevron-right" variant="micro" />
                </button>
            @else
                <button 
                    type="button"
                    aria-label="{{ __('pagination.next') }}"
                    @if (!$paginator->onLastPage())
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    @else
                        data-extreme
                        aria-disabled="true"
                        disabled
                    @endif
                >
                    <x-ui.icon name="chevron-right" variant="micro" />
                </button>
            @endif
        </div>
    @endif
</nav>