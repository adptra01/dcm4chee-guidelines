<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = ['order_id', 'radiologist', 'findings', 'impression', 'status', 'signed_by', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
