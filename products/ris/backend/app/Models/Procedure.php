<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    protected $fillable = ['code', 'name', 'body_part', 'modality'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
