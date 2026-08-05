<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['order_no', 'patient_id', 'modality', 'status', 'requested_at'];

    protected $casts = ['requested_at' => 'datetime'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function worklistItem(): HasOne
    {
        return $this->hasOne(WorklistItem::class);
    }
}
