<?php

declare(strict_types=1);

namespace Src\Components\Livewire\Concerns;

trait WithSearch
{
    public string $searchQuery = '';

    /**
     * Resets pagination when the search query is updated.
     *
     * @param  string  $property
     */
    public function updatedWithSearch($property): void
    {
        if ($property === 'searchQuery') {
            $this->resetPage();
        }
    }
}
