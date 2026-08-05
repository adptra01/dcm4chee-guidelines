<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = ['patient_id', 'name', 'sex', 'birthdate'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
