<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait WithReordering
{
    /**
     * Reorder a model inside the same scope
     *
     * @param  int  $newPosition  Zero indexed
     * @param  callable  $scope  fn (Builder $q): Builder
     */
    protected function reorderWithinScope(
        Model $model,
        int $newPosition,
        callable $scope
    ): void {
        $oldPosition = $model->order;

        if ($oldPosition === $newPosition) {
            return;
        }

        $query = $model::query();
        $scope($query);

        if ($oldPosition < $newPosition) {
            $query
                ->where('order', '>', $oldPosition)
                ->where('order', '<=', $newPosition)
                ->decrement('order');
        } else {
            $query
                ->where('order', '>=', $newPosition)
                ->where('order', '<', $oldPosition)
                ->increment('order');
        }

        $model->update(['order' => $newPosition]);
    }

    /**
     * Move a model between two scopes
     *
     * @param  callable  $fromScope  fn (Builder $q): Builder
     * @param  callable  $toScope  fn (Builder $q): Builder
     * @param  array  $scopeAttributes  attributes to change (column_id, board_id, etc)
     */
    protected function moveBetweenScopes(
        Model $model,
        callable $fromScope,
        callable $toScope,
        array $scopeAttributes,
        int $newPosition
    ): void {
        $oldPosition = $model->order;

        $from = $model::query();
        $fromScope($from);

        $from->where('order', '>', $oldPosition)
            ->decrement('order');

        $to = $model::query();
        $toScope($to);

        $to->where('order', '>=', $newPosition)
            ->increment('order');

        $model->update(array_merge($scopeAttributes, ['order' => $newPosition]));
    }

    /**
     * Safety helper for Livewire actions
     */
    protected function reorderTransaction(callable $callback): void
    {
        DB::transaction($callback);
    }
}
