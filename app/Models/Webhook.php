<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Webhook extends Model
{
    protected $fillable = [
        'api_key_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'last_triggered_at',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function logs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(32);
    }

    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? []) || in_array('*', $this->events ?? []);
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
        
        // Auto-disable after 10 consecutive failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }

    public function resetFailures(): void
    {
        $this->update(['failure_count' => 0]);
    }
}
