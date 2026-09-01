@aware(['paginator'])

<nav {{ $attributes->class('flex gap-4 items-center [@media(width<40rem)]:flex-wrap [@media(width<40rem)]:justify-center justify-between pt-3 mt-2') }} aria-label="{{ __('Pagination Navigation') }}">

   <x-ui.pagination.length-aware.variants.total class="whitespace-nowrap [@media(width<40rem)]:text-center [@media(width<40rem)]:basis-full"/>

    {{-- pagination --}}
    @if ($paginator->hasPages())
        <div 
            class="flex items-center gap-0.5 border dark:border-white/10 border-neutral-900/10 [--paginator-radius:var(--radius-box)] [--paginator-padding:--spacing(.75)] rounded-(--paginator-radius) p-(--paginator-padding)"
            role="navigation"
            aria-label="{{ __('Page navigation') }}"
        >    
            @if (!$paginator->onFirstPage())
                <button 
                    type="button"
                    class="flex justify-center items-center size-8 rounded-[calc(var(--paginator-radius)-var(--paginator-padding))] text-white/60  transition-colors cursor-pointer hover:bg-neutral-800/5 dark:hover:bg-white/5 hover:text-white"
                    wire:click="previousPage"
                    aria-label="{{ __('pagination.previous') }}"
                >
                    <x-ui.icon name="chevron-left" variant="micro" />
                </button>
            @endif

            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                
                // Show all pages if 7 or fewer
                if ($last <= 7) {
                    $pages = range(1, $last);
                } else {
                    $pages = [];
                    
                    $pages[] = 1;
                    
                    // Gap before current window
                    if ($current > 4) {
                        $pages[] = '...';
                    }
                    
                    // Current window (current ± 1)
                    $start = max(2, $current - 1);
                    $end = min($last - 1, $current + 1);
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $pages[] = $i;
                    }
                    
                    // Gap after current window
                    if ($current < $last - 3) {
                        $pages[] = '...';
                    }
                    
                    $pages[] = $last;
                }
            @endphp

            @foreach ($pages as $page)
                @if ($page === '...')
                    <x-ui.pagination.partials.dots aria-hidden="true" />
                @else
                    <x-ui.pagination.partials.page-button 
                        :page="$page" 
                        :active="$page == $paginator->currentPage()"
                        :wire:click="$page != $paginator->currentPage() ? 'gotoPage('.$page.')' : null"
                        :aria-label="$page == $paginator->currentPage() ? __('Current page, page :page', ['page' => $page]) : __('Go to page :page', ['page' => $page])"
                        :aria-current="$page == $paginator->currentPage() ? 'page' : null"
                    />
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button 
                    type="button"
                    class="flex justify-center items-center size-8 rounded-[calc(var(--paginator-radius)-var(--paginator-padding))] text-white/60  transition-colors cursor-pointer hover:bg-neutral-800/5 dark:hover:bg-white/5 hover:text-white"
                    wire:click="nextPage"
                    aria-label="{{ __('pagination.next') }}"
                >
                    <x-ui.icon name="chevron-right" variant="micro" />
                </button>
            @endif
        </div>
    @endif
</nav>