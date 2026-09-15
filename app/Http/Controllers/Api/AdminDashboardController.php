<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Order;
use App\Models\User;
use App\Models\Sport;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function stats(Request $request)
    {
        try {
            $now = Carbon::now();
            $todayStart = $now->copy()->startOfDay();
            $weekStart = $now->copy()->startOfWeek();
            $monthStart = $now->copy()->startOfMonth();
            $yearStart = $now->copy()->startOfYear();
            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

            return response()->json([
                'games' => $this->getGamesStats($todayStart, $weekStart),
                'invoices' => $this->getInvoicesStats($todayStart, $weekStart, $monthStart, $yearStart),
                'revenue' => $this->getRevenueStats($monthStart, $lastMonthStart, $lastMonthEnd),
                'api' => $this->getApiStats($todayStart, $weekStart),
                'users' => $this->getUsersStats(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'games' => ['today' => 0, 'this_week' => 0, 'live_now' => 0, 'by_sport' => []],
                'invoices' => ['today' => 0, 'this_week' => 0, 'this_month' => 0, 'this_year' => 0],
                'revenue' => ['expected' => 0, 'collected' => 0, 'pending' => 0, 'this_month' => 0, 'last_month' => 0, 'growth_percent' => 0],
                'api' => ['total_requests_today' => 0, 'total_requests_week' => 0, 'active_api_keys' => 0, 'avg_response_time_ms' => 0],
                'users' => ['total' => 0, 'online_now' => 0, 'recent_activity' => []],
            ]);
        }
    }

    private function getGamesStats($todayStart, $weekStart)
    {
        $today = Game::whereDate('game_datetime', today())->count();
        $thisWeek = Game::where('game_datetime', '>=', $weekStart)->count();
        $liveNow = Game::where('status', 'live')->count();

        // Games by sport - through leagues
        $bySport = [];
        try {
            $sports = Sport::with('leagues')->get();
            foreach ($sports as $sport) {
                $leagueIds = $sport->leagues->pluck('id')->toArray();
                $count = empty($leagueIds) ? 0 : Game::whereIn('league_id', $leagueIds)
                    ->where('game_datetime', '>=', $weekStart)
                    ->count();
                $bySport[] = [
                    'name' => $sport->name,
                    'slug' => $sport->slug,
                    'count' => $count,
                ];
            }
            usort($bySport, fn($a, $b) => $b['count'] - $a['count']);
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'live_now' => $liveNow,
            'by_sport' => $bySport,
        ];
    }

    private function getInvoicesStats($todayStart, $weekStart, $monthStart, $yearStart)
    {
        return [
            'today' => Order::whereDate('created_at', today())->count(),
            'this_week' => Order::where('created_at', '>=', $weekStart)->count(),
            'this_month' => Order::where('created_at', '>=', $monthStart)->count(),
            'this_year' => Order::where('created_at', '>=', $yearStart)->count(),
        ];
    }

    private function getRevenueStats($monthStart, $lastMonthStart, $lastMonthEnd)
    {
        $expected = Order::whereIn('status', ['pending', 'confirmed', 'completed'])->sum('total') ?? 0;
        $collected = Order::where('payment_status', 'paid')->sum('total') ?? 0;
        $pending = Order::where('payment_status', 'pending')
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('total') ?? 0;

        // Check if paid_at column exists
        $hasPaidAt = Schema::hasColumn('orders', 'paid_at');
        
        $thisMonth = 0;
        $lastMonth = 0;
        
        if ($hasPaidAt) {
            $thisMonth = Order::where('payment_status', 'paid')
                ->where('paid_at', '>=', $monthStart)
                ->sum('total') ?? 0;
            $lastMonth = Order::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('total') ?? 0;
        } else {
            // Fallback to created_at
            $thisMonth = Order::where('payment_status', 'paid')
                ->where('created_at', '>=', $monthStart)
                ->sum('total') ?? 0;
            $lastMonth = Order::where('payment_status', 'paid')
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('total') ?? 0;
        }

        $growth = 0;
        if ($lastMonth > 0) {
            $growth = (($thisMonth - $lastMonth) / $lastMonth) * 100;
        } elseif ($thisMonth > 0) {
            $growth = 100;
        }

        return [
            'expected' => round((float)$expected, 2),
            'collected' => round((float)$collected, 2),
            'pending' => round((float)$pending, 2),
            'this_month' => round((float)$thisMonth, 2),
            'last_month' => round((float)$lastMonth, 2),
            'growth_percent' => round($growth, 1),
        ];
    }

    private function getApiStats($todayStart, $weekStart)
    {
        $activeApiKeys = 0;
        try {
            if (Schema::hasTable('api_keys')) {
                $activeApiKeys = DB::table('api_keys')->where('is_active', true)->count();
            }
        } catch (\Exception $e) {}

        $requestsToday = 0;
        $requestsWeek = 0;
        $avgResponse = 0;
        
        try {
            if (Schema::hasTable('api_request_logs')) {
                $requestsToday = DB::table('api_request_logs')
                    ->where('created_at', '>=', $todayStart)
                    ->count();
                $requestsWeek = DB::table('api_request_logs')
                    ->where('created_at', '>=', $weekStart)
                    ->count();
                $avgResponse = DB::table('api_request_logs')
                    ->where('created_at', '>=', $todayStart)
                    ->avg('response_time_ms') ?? 0;
            }
        } catch (\Exception $e) {}

        return [
            'total_requests_today' => $requestsToday,
            'total_requests_week' => $requestsWeek,
            'active_api_keys' => $activeApiKeys,
            'avg_response_time_ms' => round((float)$avgResponse),
        ];
    }

    private function getUsersStats()
    {
        $onlineThreshold = Carbon::now()->subMinutes(5);
        $total = User::count();
        
        $onlineNow = 0;
        $hasLastActive = Schema::hasColumn('users', 'last_active_at');
        
        if ($hasLastActive) {
            $onlineNow = User::where('last_active_at', '>=', $onlineThreshold)->count();
        }

        $recentActivity = [];
        try {
            $query = User::orderByDesc($hasLastActive ? 'last_active_at' : 'updated_at')
                ->limit(10)
                ->get();
                
            foreach ($query as $user) {
                $lastActive = $hasLastActive ? $user->last_active_at : $user->updated_at;
                $recentActivity[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'last_active_at' => $lastActive,
                    'is_online' => $hasLastActive && $lastActive && $lastActive >= $onlineThreshold,
                ];
            }
        } catch (\Exception $e) {}

        return [
            'total' => $total,
            'online_now' => $onlineNow,
            'recent_activity' => $recentActivity,
        ];
    }
}
