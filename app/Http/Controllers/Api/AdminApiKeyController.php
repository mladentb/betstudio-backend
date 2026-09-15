<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\ApiUsageStat;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApiKeyController extends Controller
{
    /**
     * List all API keys with stats
     */
    public function index(Request $request)
    {
        $keys = ApiKey::with('company')
            ->withCount(['logs', 'webhooks'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_masked' => $key->getMaskedKey(),
                    'key' => $key->attributes['key'], // Full key for admin
                    'company' => $key->company ? ['id' => $key->company->id, 'name' => $key->company->name] : null,
                    'permissions' => $key->permissions ?? [],
                    'rate_limit' => $key->rate_limit,
                    'requests_today' => $key->requests_today,
                    'usage_percentage' => round($key->getUsagePercentage(), 1),
                    'logs_count' => $key->logs_count,
                    'webhooks_count' => $key->webhooks_count,
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'expires_at' => $key->expires_at?->toIso8601String(),
                    'is_active' => $key->is_active,
                    'created_at' => $key->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $keys,
            'meta' => [
                'available_permissions' => ApiKey::PERMISSIONS,
                'webhook_events' => ApiKey::WEBHOOK_EVENTS,
            ]
        ]);
    }

    /**
     * Create new API key
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate_limit' => 'integer|min:100|max:1000000',
            'company_id' => 'nullable|exists:companies,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', array_keys(ApiKey::PERMISSIONS)) . ',*',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $key = ApiKey::generateKey();

        $apiKey = ApiKey::create([
            'name' => $validated['name'],
            'key' => $key,
            'rate_limit' => $validated['rate_limit'] ?? 1000,
            'company_id' => $validated['company_id'] ?? null,
            'user_id' => $request->user()->id,
            'permissions' => $validated['permissions'] ?? [],
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'API key created successfully',
            'api_key' => $apiKey,
            'key' => $key, // Only returned once!
            'warning' => 'Save this key! It will not be shown again.',
        ], 201);
    }

    /**
     * Get single API key details
     */
    public function show(ApiKey $apiKey)
    {
        $apiKey->load(['company', 'webhooks']);
        
        // Get recent logs
        $recentLogs = ApiLog::where('api_key_id', $apiKey->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get usage stats for last 30 days
        $usageStats = ApiUsageStat::where('api_key_id', $apiKey->id)
            ->where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_masked' => $apiKey->getMaskedKey(),
                'company' => $apiKey->company,
                'permissions' => $apiKey->permissions ?? [],
                'rate_limit' => $apiKey->rate_limit,
                'requests_today' => $apiKey->requests_today,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'is_active' => $apiKey->is_active,
                'created_at' => $apiKey->created_at,
                'webhooks' => $apiKey->webhooks,
                'recent_logs' => $recentLogs,
                'usage_stats' => $usageStats,
            ],
            'meta' => [
                'available_permissions' => ApiKey::PERMISSIONS,
                'webhook_events' => ApiKey::WEBHOOK_EVENTS,
            ]
        ]);
    }

    /**
     * Update API key
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'rate_limit' => 'integer|min:100|max:1000000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $apiKey->update($validated);

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => $apiKey->fresh(),
        ]);
    }

    /**
     * Delete API key
     */
    public function destroy(ApiKey $apiKey)
    {
        $apiKey->delete();

        return response()->json(['message' => 'API key deleted successfully']);
    }

    /**
     * Reset daily counter
     */
    public function resetCounter(ApiKey $apiKey)
    {
        $apiKey->update(['requests_today' => 0]);

        return response()->json([
            'message' => 'Counter reset successfully',
            'api_key' => $apiKey->fresh(),
        ]);
    }

    /**
     * Regenerate API key
     */
    public function regenerate(ApiKey $apiKey)
    {
        $newKey = ApiKey::generateKey();
        $apiKey->update(['key' => $newKey]);

        return response()->json([
            'message' => 'API key regenerated successfully',
            'key' => $newKey,
            'warning' => 'Save this key! The old key is now invalid.',
        ]);
    }

    /**
     * Get API usage analytics
     */
    public function analytics(Request $request)
    {
        $days = $request->days ?? 30;
        
        // Overall stats
        $totalKeys = ApiKey::count();
        $activeKeys = ApiKey::where('is_active', true)->count();
        $totalRequestsToday = ApiKey::sum('requests_today');
        
        // Daily stats for all keys
        $dailyStats = ApiUsageStat::select(
                'date',
                DB::raw('SUM(requests_count) as total_requests'),
                DB::raw('SUM(successful_requests) as successful'),
                DB::raw('SUM(failed_requests) as failed'),
                DB::raw('AVG(avg_response_time_ms) as avg_response_time')
            )
            ->where('date', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top API keys by usage
        $topKeys = ApiKey::orderBy('requests_today', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'requests_today', 'rate_limit']);

        // Most used endpoints
        $topEndpoints = ApiLog::select('endpoint', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('endpoint')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Error rate by status code
        $statusCodes = ApiLog::select('status_code', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('status_code')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'overview' => [
                    'total_keys' => $totalKeys,
                    'active_keys' => $activeKeys,
                    'requests_today' => $totalRequestsToday,
                ],
                'daily_stats' => $dailyStats,
                'top_keys' => $topKeys,
                'top_endpoints' => $topEndpoints,
                'status_codes' => $statusCodes,
            ]
        ]);
    }

    /**
     * Get logs for specific API key
     */
    public function logs(Request $request, ApiKey $apiKey)
    {
        $perPage = $request->per_page ?? 50;
        
        $logs = ApiLog::where('api_key_id', $apiKey->id)
            ->when($request->endpoint, fn($q) => $q->where('endpoint', 'like', "%{$request->endpoint}%"))
            ->when($request->status_code, fn($q) => $q->where('status_code', $request->status_code))
            ->when($request->date_from, fn($q) => $q->where('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($logs);
    }

    // ==========================================
    // WEBHOOKS
    // ==========================================

    /**
     * List webhooks for API key
     */
    public function webhooks(ApiKey $apiKey)
    {
        $webhooks = Webhook::where('api_key_id', $apiKey->id)
            ->withCount('logs')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $webhooks,
            'meta' => [
                'available_events' => ApiKey::WEBHOOK_EVENTS,
            ]
        ]);
    }

    /**
     * Create webhook
     */
    public function createWebhook(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', array_keys(ApiKey::WEBHOOK_EVENTS)) . ',*',
        ]);

        $webhook = Webhook::create([
            'api_key_id' => $apiKey->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => Webhook::generateSecret(),
            'events' => $validated['events'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Webhook created successfully',
            'webhook' => $webhook,
            'secret' => $webhook->secret, // Only returned once
            'warning' => 'Save the secret! It will not be shown again.',
        ], 201);
    }

    /**
     * Update webhook
     */
    public function updateWebhook(Request $request, Webhook $webhook)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'url' => 'url|max:500',
            'events' => 'array',
            'events.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $webhook->update($validated);

        return response()->json([
            'message' => 'Webhook updated successfully',
            'webhook' => $webhook->fresh(),
        ]);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(Webhook $webhook)
    {
        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted successfully']);
    }

    /**
     * Test webhook
     */
    public function testWebhook(Webhook $webhook)
    {
        $payload = [
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook from BetStudio',
            ]
        ];

        try {
            $response = \Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Secret' => $webhook->secret,
                    'X-Webhook-Event' => 'test',
                ])
                ->post($webhook->url, $payload);

            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event_type' => 'test',
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'success' => $response->successful(),
                'created_at' => now(),
            ]);

            if ($response->successful()) {
                $webhook->resetFailures();
                $webhook->update(['last_triggered_at' => now()]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook test successful',
                    'status_code' => $response->status(),
                ]);
            } else {
                $webhook->incrementFailure();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook returned error',
                    'status_code' => $response->status(),
                    'response' => substr($response->body(), 0, 500),
                ], 422);
            }
        } catch (\Exception $e) {
            $webhook->incrementFailure();
            
            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event_type' => 'test',
                'payload' => $payload,
                'response_status' => 0,
                'response_body' => $e->getMessage(),
                'success' => false,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reach webhook URL',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get webhook logs
     */
    public function webhookLogs(Webhook $webhook)
    {
        $logs = WebhookLog::where('webhook_id', $webhook->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
