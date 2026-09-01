<?php

declare(strict_types=1);

namespace Src\Components\Livewire\Concerns;

use Livewire\WithPagination as LivewirePagination;

trait WithPagination
{
    use LivewirePagination;

    public $perPage = 10;

    /**
     * Resets pagination when the search query is updated.
     *
     * @param  string  $property
     */
    public function updatedWithPagination($property): void
    {
        if ($property === 'perPage') {
            $this->resetPage();
        }
    }
}
