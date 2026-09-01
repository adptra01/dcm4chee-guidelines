<?php

declare(strict_types=1);

namespace Src\Components\Livewire\Concerns;

trait WithSorting
{
    public string $sortBy = '';

    public string $sortDir = 'asc';

    public function sortByColumn(string $column, ?string $dir = null): void
    {
        if ($dir === '') {
            if ($this->sortBy === $column) {
                $this->clearSorting();
            }

            return;
        }

        if ($dir && $dir !== '') {
            $this->sortBy = $column;
            $this->sortDir = $dir;

            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function clearSorting(): void
    {
        $this->sortBy = '';
        $this->sortDir = 'asc';
    }

    public function applySorting($query)
    {
        if (! filled($this->sortBy)) {
            return $query;
        }

        if (! $this->isSortableColumn($this->sortBy)) {
            return $query;
        }

        if (method_exists($this, 'sortUsingAlgorithm')) {
            return $this->sortUsingAlgorithm($query, $this->sortBy, $this->sortDir) ?? $this->defaultSort($query);
        }

        return $this->defaultSort($query);
    }

    protected function defaultSort($query)
    {
        return $query->orderBy($this->sortBy, $this->sortDir);
    }

    protected function isSortableColumn(string $column): bool
    {
        $sortableCols = $this->sortableColumns();

        if (empty($sortableCols)) {
            return true;
        }

        return in_array($column, $sortableCols);
    }

    protected function sortableColumns(): array
    {
        return [];
    }
}
