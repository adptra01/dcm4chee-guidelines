<?php

declare(strict_types=1);

namespace Src\Components\Livewire\Concerns;

trait WithSelection
{
    public array $selectedIds = [];

    public array $visibleIds = [];

    // while we can handle them from here but let's keep all interaction cames from the front end
    public function selectAll()
    {
        $this->dispatch('selectAll');
    }

    public function deselectAll()
    {
        $this->selectedIds = [];
        $this->dispatch('deselectAll');
    }

    protected function applySelection($query)
    {
        return $query->whereIn('id', $this->selectedIds);
    }
}
