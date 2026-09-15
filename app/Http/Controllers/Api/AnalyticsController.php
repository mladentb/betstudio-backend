<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Invoice;
use App\Models\PurchasedGame;
use App\Models\Game;
use App\Models\League;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $period = $request->get('period', '6months');
        $startDate = $this->getStartDate($period);
        $endDate = now();

        // Overview stats - ALL orders
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])->sum('total');
        $paidRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');
        $unpaidRevenue = Order::where('payment_status', 'unpaid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $paidOrders = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $unpaidOrders = Order::where('payment_status', 'unpaid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Invoices
        $totalInvoices = Invoice::whereBetween('created_at', [$startDate, $endDate])->count();
        $paidInvoices = Invoice::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $pendingInvoices = Invoice::whereIn('status', ['sent', 'draft'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalUsers = User::where('role', '!=', 'admin')->count();
        $newUsers = User::where('role', '!=', 'admin')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $buyingUsers = Order::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');

        $conversionRate = $totalUsers > 0 ? round(($buyingUsers / $totalUsers) * 100, 1) : 0;
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
        $paymentRate = $totalOrders > 0 ? round(($paidOrders / $totalOrders) * 100, 1) : 0;

        return response()->json([
            'data' => [
                'overview' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'paid_revenue' => round($paidRevenue, 2),
                    'unpaid_revenue' => round($unpaidRevenue, 2),
                    'total_orders' => $totalOrders,
                    'paid_orders' => $paidOrders,
                    'unpaid_orders' => $unpaidOrders,
                    'total_invoices' => $totalInvoices,
                    'paid_invoices' => $paidInvoices,
                    'pending_invoices' => $pendingInvoices,
                    'total_users' => $totalUsers,
                    'new_users' => $newUsers,
                    'buying_users' => $buyingUsers,
                    'conversion_rate' => $conversionRate,
                    'payment_rate' => $paymentRate,
                    'avg_order_value' => $avgOrderValue,
                    'avg_ltv' => $buyingUsers > 0 ? round($totalRevenue / $buyingUsers, 2) : 0,
                ],
                'revenue_by_month' => $this->getRevenueByMonth($startDate, $endDate),
                'orders_by_status' => $this->getOrdersByStatus($startDate, $endDate),
                'revenue_by_sport' => $this->getRevenueBySport($startDate, $endDate),
                'revenue_by_league' => $this->getRevenueByLeague($startDate, $endDate),
                'revenue_by_payment_method' => $this->getRevenueByPaymentMethod($startDate, $endDate),
                'revenue_by_day_type' => $this->getRevenueByDayType($startDate, $endDate),
                'top_games' => $this->getTopGames($startDate, $endDate),
                'user_growth' => $this->getUserGrowth($startDate, $endDate),
                'recent_orders' => $this->getRecentOrders(),
            ]
        ]);
    }

    private function getStartDate($period)
    {
        return match($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '3months' => now()->subMonths(3),
            '6months' => now()->subMonths(6),
            '1year' => now()->subYear(),
            'all' => now()->subYears(10),
            default => now()->subMonths(6),
        };
    }

    private function getRevenueByMonth($startDate, $endDate)
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, 
                         SUM(total) as total_revenue,
                         SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid_revenue,
                         SUM(CASE WHEN payment_status = 'unpaid' THEN total ELSE 0 END) as unpaid_revenue,
                         COUNT(*) as orders")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM')")
            ->orderBy('month')
            ->get();
    }

    private function getOrdersByStatus($startDate, $endDate)
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("payment_status, COUNT(*) as count, SUM(total) as revenue")
            ->groupBy('payment_status')
            ->get()
            ->map(function($item) {
                $labels = [
                    'unpaid' => 'Neplaćeno',
                    'paid' => 'Plaćeno',
                    'refunded' => 'Refundirano',
                    'cancelled' => 'Otkazano',
                ];
                $colors = [
                    'unpaid' => '#f59e0b',
                    'paid' => '#10b981',
                    'refunded' => '#3b82f6',
                    'cancelled' => '#ef4444',
                ];
                $item->label = $labels[$item->payment_status] ?? $item->payment_status;
                $item->color = $colors[$item->payment_status] ?? '#6b7280';
                return $item;
            });
    }

    private function getRevenueBySport($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('games', 'order_items.game_id', '=', 'games.id')
            ->join('leagues', 'games.league_id', '=', 'leagues.id')
            ->join('sports', 'leagues.sport_id', '=', 'sports.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw('sports.name as sport, sports.slug, 
                         SUM(order_items.total_price) as revenue, 
                         SUM(CASE WHEN orders.payment_status = \'paid\' THEN order_items.total_price ELSE 0 END) as paid_revenue,
                         COUNT(*) as sales')
            ->groupBy('sports.id', 'sports.name', 'sports.slug')
            ->orderByDesc('revenue')
            ->get();
    }

    private function getRevenueByLeague($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('games', 'order_items.game_id', '=', 'games.id')
            ->join('leagues', 'games.league_id', '=', 'leagues.id')
            ->join('sports', 'leagues.sport_id', '=', 'sports.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw('leagues.name as league, sports.name as sport, 
                         SUM(order_items.total_price) as revenue, 
                         SUM(CASE WHEN orders.payment_status = \'paid\' THEN order_items.total_price ELSE 0 END) as paid_revenue,
                         COUNT(*) as sales')
            ->groupBy('leagues.id', 'leagues.name', 'sports.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
    }

    private function getRevenueByPaymentMethod($startDate, $endDate)
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, 
                         SUM(total) as revenue, 
                         SUM(CASE WHEN payment_status = \'paid\' THEN total ELSE 0 END) as paid_revenue,
                         COUNT(*) as orders,
                         SUM(CASE WHEN payment_status = \'paid\' THEN 1 ELSE 0 END) as paid_orders')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(function($item) {
                $labels = [
                    'bank_transfer' => 'Banka',
                    'card' => 'Kartica',
                    'crypto' => 'Crypto',
                    'paypal' => 'PayPal',
                ];
                $item->label = $labels[$item->payment_method] ?? $item->payment_method;
                return $item;
            });
    }

    private function getRevenueByDayType($startDate, $endDate)
    {
        $weekday = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('games', 'order_items.game_id', '=', 'games.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereRaw("EXTRACT(DOW FROM games.game_datetime) NOT IN (0, 6)")
            ->selectRaw('SUM(order_items.total_price) as total, 
                         SUM(CASE WHEN orders.payment_status = \'paid\' THEN order_items.total_price ELSE 0 END) as paid')
            ->first();

        $weekend = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('games', 'order_items.game_id', '=', 'games.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereRaw("EXTRACT(DOW FROM games.game_datetime) IN (0, 6)")
            ->selectRaw('SUM(order_items.total_price) as total, 
                         SUM(CASE WHEN orders.payment_status = \'paid\' THEN order_items.total_price ELSE 0 END) as paid')
            ->first();

        return [
            ['type' => 'weekday', 'label' => 'Radni dani', 'revenue' => round($weekday->total ?? 0, 2), 'paid' => round($weekday->paid ?? 0, 2)],
            ['type' => 'weekend', 'label' => 'Vikend', 'revenue' => round($weekend->total ?? 0, 2), 'paid' => round($weekend->paid ?? 0, 2)],
        ];
    }

    private function getTopGames($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('games', 'order_items.game_id', '=', 'games.id')
            ->join('leagues', 'games.league_id', '=', 'leagues.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw("games.home_team || ' vs ' || games.away_team as game, 
                         leagues.name as league, 
                         SUM(order_items.total_price) as revenue, 
                         SUM(CASE WHEN orders.payment_status = 'paid' THEN order_items.total_price ELSE 0 END) as paid_revenue,
                         COUNT(*) as sales")
            ->groupBy('games.id', 'games.home_team', 'games.away_team', 'leagues.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
    }

    private function getUserGrowth($startDate, $endDate)
    {
        return User::where('role', '!=', 'admin')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, COUNT(*) as users")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM')")
            ->orderBy('month')
            ->get();
    }

    private function getRecentOrders()
    {
        return Order::with(['user:id,name,email', 'invoice:id,order_id,invoice_number,status'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user' => $order->user->name,
                    'total' => $order->total,
                    'payment_status' => $order->payment_status,
                    'invoice' => $order->invoice?->invoice_number,
                    'invoice_status' => $order->invoice?->status,
                    'created_at' => $order->created_at->format('d.m.Y H:i'),
                ];
            });
    }
}
