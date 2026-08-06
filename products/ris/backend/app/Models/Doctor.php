<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = ['name', 'specialization', 'phone', 'nidn'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}