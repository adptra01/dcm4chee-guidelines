@aware([
    'paginator',
    'options' => [10, 15, 20, 25, 30, 35, 40]
])

<nav {{ $attributes->class('flex [@media(width<40rem)]:flex-col gap-2  items-center justify-center rounded-box pt-3 mt-2') }} aria-label="{{ __('Pagination Navigation') }}">

    <div class="flex justify-between items-center w-full">
        <x-ui.pagination.length-aware.variants.total class="[@media(width<40rem)]:ml-2"/>

        <x-ui.pagination.length-aware.variants.page-of class="sm:hidden mr-2"/>
    </div>

    <div class="flex justify-between items-center w-full">
        <div>
            <label for="per-page-select" class="sr-only">{{ __('Items per page') }}</label>
            <x-ui.select
                preventLoading
                id="per-page-select" 
                aria-label="{{ __('Items per page') }}"
                wire:model.live="perPage" 
                size="sm"
                class="max-w-sm w-20 [&_[data-slot=select-control]]:bg-transparent [&_[data-slot=select-control]]:pl-3"
            >
                @foreach ($options as $size)
                    <x-ui.select.option :wire:key="$size" :value="$size">
                        {{ $size }}
                    </x-ui.select.option>
                @endforeach
            </x-ui.select>
        </div>

        <x-ui.pagination.length-aware.variants.page-of class="[@media(width<40rem)]:hidden mx-4 whitespace-nowrap"/>

        <div 
            class="
                justify-center sm:basis-auto ml-autos flex gap-1 
                *:ring-1
                *:ring-neutral-900/10 *:dark:ring-white/10 
                *:bg-[--alpha(var(--color-neutral-900)/5%)]! *:dark:bg-[--alpha(var(--color-white)/5%)]! 
                *:hover:bg-[--alpha(var(--color-neutral-900)/7%)]! *:dark:hover:bg-[--alpha(var(--color-white)/7%)]! 
                *:transition-color 
            "
        >
            {{-- first --}}
            <x-ui.button
                icon="chevron-double-left"
                size="sm"
                variant="soft"
                iconVariant="micro"
                aria-label="{{ __('First page') }}"
                wire:loading
                wire:target="gotoPage(1)"
                :disabled="$paginator->onFirstPage()"
                :aria-disabled="$paginator->onFirstPage() ? 'true' : 'false'"
                wire:click="gotoPage(1)"
            />

            {{-- previous --}}
            <x-ui.button
                icon="chevron-left"
                size="sm"
                variant="soft"
                iconVariant="micro"
                aria-label="{{ __('pagination.previous') }}"
                wire:loading
                wire:target="previousPage"
                :disabled="$paginator->onFirstPage()"
                :aria-disabled="$paginator->onFirstPage() ? 'true' : 'false'"
                wire:click="previousPage"
            />

            {{-- next --}}
            <x-ui.button
                icon="chevron-right"
                size="sm"
                variant="soft"
                iconVariant="micro"
                aria-label="{{ __('pagination.next') }}"
                wire:loading
                wire:target="nextPage"
                :disabled="$paginator->onLastPage()"
                :aria-disabled="$paginator->onLastPage() ? 'true' : 'false'"
                wire:click="nextPage"
            />

            {{-- last --}}
            <x-ui.button
                icon="chevron-double-right"
                size="sm"
                variant="soft"
                iconVariant="micro"
                aria-label="{{ __('Last page') }}"
                wire:loading
                wire:target="gotoPage({{ $paginator->lastPage() }})"
                :disabled="$paginator->onLastPage()"
                :aria-disabled="$paginator->onLastPage() ? 'true' : 'false'"
                wire:click="gotoPage({{ $paginator->lastPage() }})"
            />
        </div>

    </div>
   
</nav>