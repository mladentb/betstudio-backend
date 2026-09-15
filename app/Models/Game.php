<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Game extends Model
{
    protected $fillable = [
        'league_id',
        'api_game_id',
        'home_team',
        'away_team',
        'game_datetime',
        'venue',
        'status',
        'home_score',
        'away_score',
        'live_data',
        'is_available_for_sale'
    ];

    protected $casts = [
        'game_datetime' => 'datetime',
        'live_data' => 'array',
        'is_available_for_sale' => 'boolean',
        'home_score' => 'integer',
        'away_score' => 'integer'
    ];

    protected $appends = ['price', 'is_weekend'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function getIsWeekendAttribute(): bool
    {
        if (!$this->game_datetime) return false;
        $dayOfWeek = Carbon::parse($this->game_datetime)->dayOfWeek;
        return $dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY;
    }

    public function getPriceAttribute(): ?float
    {
        $leaguePrice = LeaguePrice::where('league_id', $this->league_id)->first();
        
        if (!$leaguePrice) return null;

        return $this->is_weekend 
            ? (float) $leaguePrice->weekend_price 
            : (float) $leaguePrice->weekday_price;
    }
}
