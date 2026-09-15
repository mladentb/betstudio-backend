<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'account_type',
        'role',
        'phone',
        'company_id',
        'is_company_owner',
        'company_name',
        'company_address',
        'city',
        'zip_code',
        'country',
        'country_code',
        'vat_number',
        'registration_number',
        'duns_number',
        'continent',
        'preferred_currency',
        'wallet_address',
        'location',
        'is_active',
        'is_verified',
        'terms_accepted_at',
        'approved_at',
        'approved_by',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_company_owner' => 'boolean',
        ];
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function teamMembers()
    {
        return $this->hasMany(User::class, 'company_id', 'company_id')
            ->where('id', '!=', $this->id);
    }

    // Role helpers
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCompanyOwner(): bool
    {
        return $this->is_company_owner === true;
    }

    public function isBuyer(): bool
    {
        return $this->account_type === 'buyer';
    }

    public function isSeller(): bool
    {
        return $this->account_type === 'seller';
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isPending(): bool
    {
        return $this->approved_at === null && $this->is_active;
    }
}
