@aware(['paginator'])

<nav 
    {{ $attributes->class([
        "flex items-center justify-between pt-3 mt-2 [&_button]:rounded-box",
        "[&_button]:border [&_button]:dark:border-white/10 [&_button]:border-neutral-900/10",
        "[&_button]:dark:bg-white/5 [&_button]:bg-neutral-900/5" 
    ]) }} 
    aria-label="{{ __('Pagination Navigation') }}"
>
    @if ($paginator->hasPages())
        {{-- previous --}}
        <div>
            @if (method_exists($paginator, 'getCursorName'))
                @if (!$paginator->onFirstPage())
                    <x-ui.button
                        icon="chevron-left"
                        size="sm"
                        iconVariant="micro"
                        variant="soft"
                        aria-label="{{ __('pagination.previous') }}"
                        wire:loading
                        wire:target="setPage('{{ $paginator->previousCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->previousCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                    />
                @else  
                    <x-ui.button
                        icon="chevron-left"
                        size="sm"
                        iconVariant="micro"
                        variant="soft"
                        aria-label="{{ __('pagination.previous') }}"
                        aria-disabled="true"
                        disabled
                    />
                @endif
            @else
                <x-ui.button
                    icon="chevron-left"
                    size="sm"
                    iconVariant="micro"
                    variant="soft"
                    aria-label="{{ __('pagination.previous') }}"
                    wire:loading
                    wire:target="previousPage('{{ $paginator->getPageName() }}')"
                    :disabled="$paginator->onFirstPage()"
                    :aria-disabled="$paginator->onFirstPage() ? 'true' : 'false'"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                />
            @endif
        </div>

        {{-- next --}}
        <div>
            @if (method_exists($paginator, 'getCursorName'))
                @if (!$paginator->onLastPage())
                    <x-ui.button
                        icon="chevron-right"
                        size="sm"
                        iconVariant="micro"
                        variant="soft"
                        aria-label="{{ __('pagination.next') }}"
                        wire:loading
                        wire:target="setPage('{{ $paginator->nextCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->nextCursor()->encode() }}', '{{ $paginator->getCursorName() }}')"
                    />
                @else
                    <x-ui.button
                        icon="chevron-right"
                        size="sm"
                        iconVariant="micro"
                        variant="soft"
                        aria-label="{{ __('pagination.next') }}"
                        aria-disabled="true"
                        disabled
                    />
                @endif
            @else
                <x-ui.button
                    icon="chevron-right"
                    size="sm"
                    iconVariant="micro"
                    variant="soft"
                    aria-label="{{ __('pagination.next') }}"
                    wire:loading
                    wire:target="nextPage('{{ $paginator->getPageName() }}')"
                    :disabled="$paginator->onLastPage()"
                    :aria-disabled="$paginator->onLastPage() ? 'true' : 'false'"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                />
            @endif
        </div>
    @endif
</nav>