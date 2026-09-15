<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageStat extends Model
{
    protected $table = 'api_usage_stats';
    public $timestamps = false;
    
    protected $fillable = [
        'api_key_id',
        'date',
        'requests_count',
        'successful_requests',
        'failed_requests',
        'avg_response_time_ms',
        'endpoints_usage',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'endpoints_usage' => 'array',
        'created_at' => 'datetime',
    ];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }
}
