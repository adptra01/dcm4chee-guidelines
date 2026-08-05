<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorklistItem extends Model
{
    protected $fillable = ['order_id', 'scheduled_aet', 'scheduled_at', 'status'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
