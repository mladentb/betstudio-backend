<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaguePrice extends Model
{
    protected $fillable = [
        'league_id', 'weekday_price', 'weekend_price', 'currency', 'is_active'
    ];

    protected $casts = [
        'weekday_price' => 'decimal:2',
        'weekend_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }
}
