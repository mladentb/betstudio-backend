<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'api_key_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'request_body',
        'created_at',
    ];

    protected $casts = [
        'request_body' => 'array',
        'created_at' => 'datetime',
    ];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }
}
