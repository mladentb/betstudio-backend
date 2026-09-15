<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'address',
        'city',
        'zip_code',
        'country',
        'country_code',
        'vat_number',
        'registration_number',
        'duns_number',
        'website',
        'bank_name',
        'bank_account',
        'swift_bic',
        'logo_url',
        'continents',
        'is_active',
        'is_default',
        'discount_percent',
        'owner_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'continents' => 'array',
        'discount_percent' => 'decimal:2',
    ];

    // Accessor to ensure continents is always an array
    public function getContinentsAttribute($value)
    {
        if (is_null($value)) return [];
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
