<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'api_source',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }
}