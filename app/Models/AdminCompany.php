<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminCompany extends Model
{
    protected $table = 'admin_companies';

    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'website',
        'logo_url',
        'address',
        'city',
        'zip_code',
        'country',
        'country_code',
        'vat_number',
        'registration_number',
        'bank_name',
        'bank_account',
        'bank_swift',
        'bank_iban',
        'regions_covered',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'regions_covered' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // Get the default company
    public static function getDefault()
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    // Get company for specific region
    public static function getForRegion(string $region)
    {
        return static::where('is_active', true)
            ->whereJsonContains('regions_covered', $region)
            ->first() ?? static::getDefault();
    }

    // Set this company as default (unset others)
    public function setAsDefault()
    {
        static::where('is_default', true)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
