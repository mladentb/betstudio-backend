<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\PurchasedGame;
use App\Mail\PurchaseConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items', 'invoice']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($request->get('per_page', 20));
    }

    public function show(Order $order)
    {
        return response()->json([
            'data' => $order->load(['user', 'items.game', 'invoice.sellerCompany'])
        ]);
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:pending,unpaid,paid,refunded,cancelled',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        $wasPaid = $order->payment_status === 'paid';
        $nowPaid = $validated['payment_status'] === 'paid';

        DB::transaction(function () use ($order, $validated, $wasPaid, $nowPaid) {
            $order->update([
                'status' => $nowPaid ? 'confirmed' : ($validated['payment_status'] === 'cancelled' ? 'cancelled' : 'pending'),
                'payment_status' => $validated['payment_status'],
                'payment_method' => $validated['payment_method'] ?? $order->payment_method,
                'paid_at' => $nowPaid ? now() : null,
                'notes' => $validated['notes'] ?? $order->notes,
            ]);

            // If payment just became 'paid', create PurchasedGame records
            if (!$wasPaid && $nowPaid) {
                foreach ($order->items as $item) {
                    // Check if already exists (safety)
                    $exists = PurchasedGame::where('order_id', $order->id)
                        ->where('game_id', $item->game_id)
                        ->exists();
                    
                    if (!$exists) {
                        PurchasedGame::create([
                            'user_id' => $order->user_id,
                            'order_id' => $order->id,
                            'game_id' => $item->game_id,
                            'price_paid' => $item->total_price,
                            'stats_token' => PurchasedGame::generateStatsToken(),
                            'stats_url' => config('app.url') . '/stats/' . PurchasedGame::generateStatsToken(),
                        ]);
                    }
                }

                // Send purchase confirmation email
                try {
                    Mail::to($order->user->email)->send(new PurchaseConfirmation($order->user, [
                        'id' => $order->order_number,
                        'date' => $order->created_at->format('d.m.Y H:i'),
                        'items' => $order->items->map(fn($i) => ['name' => $i->description, 'price' => '€' . $i->total_price]),
                        'discount' => $order->discount_amount > 0 ? "-€{$order->discount_amount} ({$order->discount_percent}%)" : null,
                        'total' => '€' . $order->total,
                        'status' => 'Plaćeno',
                    ]));
                } catch (\Exception $e) {
                    \Log::error('Failed to send purchase confirmation email: ' . $e->getMessage());
                }
            }

            // If payment was cancelled or refunded, remove PurchasedGame records
            if ($validated['payment_status'] === 'cancelled' || $validated['payment_status'] === 'refunded') {
                PurchasedGame::where('order_id', $order->id)->delete();
            }

            // Update invoice status if exists
            if ($order->invoice) {
                $order->invoice->update([
                    'status' => $nowPaid ? 'paid' : 'sent',
                    'paid_at' => $nowPaid ? now() : null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Status uplate ažuriran',
            'data' => $order->fresh()->load(['user', 'items', 'invoice'])
        ]);
    }

    public function generateInvoice(Request $request, Order $order)
    {
        if ($order->invoice) {
            return response()->json(['message' => 'Invoice already exists', 'data' => $order->invoice], 400);
        }

        $validated = $request->validate([
            'seller_company_id' => 'required|exists:companies,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'due_days' => 'nullable|integer|min:1|max:90',
            'notes' => 'nullable|string'
        ]);

        $sellerCompany = Company::find($validated['seller_company_id']);
        $taxRate = $validated['tax_rate'] ?? 20;
        $dueDays = $validated['due_days'] ?? 15;
        
        // Use provided discount or order's discount
        $discountPercent = $validated['discount_percent'] ?? $order->discount_percent ?? 0;

        $subtotal = $order->subtotal;
        $discountAmount = $subtotal * ($discountPercent / 100);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = $afterDiscount * ($taxRate / 100);
        $total = $afterDiscount + $taxAmount;

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'seller_company_id' => $sellerCompany->id,
            'buyer_company_name' => $order->user->company_name,
            'buyer_address' => $order->user->company_address . ', ' . $order->user->city . ', ' . $order->user->country,
            'buyer_vat' => $order->user->vat_number,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => $order->currency,
            'status' => 'sent',
            'issue_date' => now(),
            'due_date' => now()->addDays($dueDays),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Invoice generated',
            'data' => $invoice->load('sellerCompany')
        ], 201);
    }

    public function statistics(Request $request)
    {
        $fromDate = $request->get('from_date', now()->startOfMonth());
        $toDate = $request->get('to_date', now());

        $orders = Order::whereBetween('created_at', [$fromDate, $toDate]);
        $paidOrders = Order::whereBetween('created_at', [$fromDate, $toDate])->where('payment_status', 'paid');

        // Monthly revenue for chart
        $monthlyRevenue = Order::where('payment_status', 'paid')
            ->whereYear('created_at', now()->year)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, SUM(total) as revenue, COUNT(*) as orders")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM')")
            ->orderBy('month')
            ->get();

        return response()->json([
            'data' => [
                'total_orders' => $orders->count(),
                'total_revenue' => $orders->sum('total'),
                'paid_revenue' => $paidOrders->sum('total'),
                'unpaid_revenue' => Order::whereBetween('created_at', [$fromDate, $toDate])->where('payment_status', 'unpaid')->sum('total'),
                'pending_orders' => Order::where('payment_status', 'unpaid')->count(),
                'paid_orders' => $paidOrders->count(),
                'average_order_value' => $orders->count() > 0 ? round($orders->sum('total') / $orders->count(), 2) : 0,
                'monthly_revenue' => $monthlyRevenue,
            ]
        ]);
    }
}
