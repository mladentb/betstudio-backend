<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\ApiUsageStat;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $apiKeyString = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$apiKeyString) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide X-API-Key header or api_key query parameter',
                'docs' => 'https://api.betstudio.com/docs'
            ], 401);
        }

        $key = ApiKey::where('key', $apiKeyString)->first();

        if (!$key) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid'
            ], 401);
        }

        if (!$key->isValid()) {
            return response()->json([
                'error' => 'API key inactive or expired',
                'message' => 'Your API key is no longer valid',
                'expired_at' => $key->expires_at?->toIso8601String()
            ], 403);
        }

        if ($key->hasReachedRateLimit()) {
            $this->logRequest($key, $request, 429, $startTime);
            
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "You have exceeded your daily limit of {$key->rate_limit} requests",
                'limit' => $key->rate_limit,
                'used' => $key->requests_today,
                'reset_at' => now()->endOfDay()->toIso8601String()
            ], 429);
        }

        // Check endpoint permission
        $permission = $this->getRequiredPermission($request);
        if ($permission && !$key->hasPermission($permission)) {
            $this->logRequest($key, $request, 403, $startTime);
            
            return response()->json([
                'error' => 'Permission denied',
                'message' => "Your API key doesn't have permission: {$permission}",
                'required_permission' => $permission
            ], 403);
        }

        // Store key for later use
        $request->attributes->set('api_key', $key);

        // Increment request count
        $key->incrementRequests();

        // Execute request
        $response = $next($request);
        
        // Log the request
        $statusCode = $response->getStatusCode();
        $this->logRequest($key, $request, $statusCode, $startTime);
        
        // Update daily stats
        $this->updateDailyStats($key, $statusCode, $startTime);

        // Add rate limit headers
        return $response->withHeaders([
            'X-RateLimit-Limit' => $key->rate_limit,
            'X-RateLimit-Remaining' => max(0, $key->rate_limit - $key->requests_today),
            'X-RateLimit-Reset' => now()->endOfDay()->timestamp,
            'X-Request-Id' => uniqid('req_'),
        ]);
    }

    private function getRequiredPermission(Request $request): ?string
    {
        $path = $request->path();
        
        $permissionMap = [
            'v1/sports' => 'sports:read',
            'v1/leagues' => 'leagues:read',
            'v1/games' => 'games:read',
            'v1/games/live' => 'games:live',
            'v1/games/today' => 'games:read',
            'v1/games/upcoming' => 'games:read',
            'v1/stats' => 'stats:read',
        ];

        foreach ($permissionMap as $pattern => $permission) {
            if (str_starts_with($path, $pattern)) {
                return $permission;
            }
        }

        return null;
    }

    private function logRequest(ApiKey $key, Request $request, int $statusCode, float $startTime): void
    {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        
        try {
            ApiLog::create([
                'api_key_id' => $key->id,
                'endpoint' => '/' . $request->path(),
                'method' => $request->method(),
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'request_body' => $request->except(['api_key']),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the request if logging fails
            \Log::error('API Log failed: ' . $e->getMessage());
        }
    }

    private function updateDailyStats(ApiKey $key, int $statusCode, float $startTime): void
    {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        $isSuccess = $statusCode >= 200 && $statusCode < 400;
        
        try {
            DB::transaction(function () use ($key, $isSuccess, $responseTime) {
                $stat = ApiUsageStat::firstOrCreate(
                    ['api_key_id' => $key->id, 'date' => today()],
                    ['requests_count' => 0, 'successful_requests' => 0, 'failed_requests' => 0, 'avg_response_time_ms' => 0, 'endpoints_usage' => []]
                );

                $newCount = $stat->requests_count + 1;
                $newAvg = (($stat->avg_response_time_ms * $stat->requests_count) + $responseTime) / $newCount;

                $stat->update([
                    'requests_count' => $newCount,
                    'successful_requests' => $stat->successful_requests + ($isSuccess ? 1 : 0),
                    'failed_requests' => $stat->failed_requests + ($isSuccess ? 0 : 1),
                    'avg_response_time_ms' => (int)$newAvg,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('API Stats update failed: ' . $e->getMessage());
        }
    }
}
