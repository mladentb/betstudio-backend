<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'company_id',
        'user_id',
        'permissions',
        'rate_limit',
        'requests_today',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'rate_limit' => 'integer',
        'requests_today' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['key'];

    // Available permissions
    public const PERMISSIONS = [
        'sports:read' => 'View sports list',
        'leagues:read' => 'View leagues list',
        'games:read' => 'View games list',
        'games:live' => 'View live games',
        'stats:read' => 'View statistics',
        'webhooks:manage' => 'Manage webhooks',
    ];

    // Available events for webhooks
    public const WEBHOOK_EVENTS = [
        'game.started' => 'When a game starts (goes live)',
        'game.finished' => 'When a game finishes',
        'game.score_updated' => 'When score changes',
        'game.created' => 'When a new game is added',
        'game.updated' => 'When game details change',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(ApiLog::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function usageStats()
    {
        return $this->hasMany(ApiUsageStat::class);
    }

    public static function generateKey(): string
    {
        return 'bs_' . Str::random(32);
    }

    public function hasPermission(string $permission): bool
    {
        // Empty permissions means all access
        if (empty($this->permissions)) {
            return true;
        }
        
        // Check for wildcard
        if (in_array('*', $this->permissions)) {
            return true;
        }
        
        return in_array($permission, $this->permissions);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasReachedRateLimit(): bool
    {
        return $this->requests_today >= $this->rate_limit;
    }

    public function incrementRequests(): void
    {
        $this->increment('requests_today');
        $this->update(['last_used_at' => now()]);
    }

    public function getUsagePercentage(): float
    {
        if ($this->rate_limit <= 0) return 0;
        return min(100, ($this->requests_today / $this->rate_limit) * 100);
    }

    public function getMaskedKey(): string
    {
        $key = $this->attributes['key'] ?? '';
        if (strlen($key) < 10) return '••••••••';
        return substr($key, 0, 6) . '••••••••••••••••' . substr($key, -4);
    }
}
