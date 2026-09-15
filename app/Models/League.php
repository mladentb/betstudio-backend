<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class League extends Model
{
    protected $fillable = [
        'sport_id',
        'name',
        'name_en',
        'slug',
        'api_league_id',
        'api_endpoint',
        'api_source',
        'api_token',
        'gender',
        'country_code',
        'country_name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected $hidden = [
        'api_token',
    ];

    /**
     * Get the route key for the model.
     * Using 'id' for admin operations, slug is still used for public URLs
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
    
    /**
     * Resolve route binding - accept both ID and slug
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // If it's numeric, find by ID
        if (is_numeric($value)) {
            return $this->where('id', $value)->firstOrFail();
        }
        
        // Otherwise, find by slug
        return $this->where('slug', $value)->firstOrFail();
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function price()
    {
        return $this->hasOne(LeaguePrice::class);
    }
}
