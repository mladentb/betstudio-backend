<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Game;
use App\Models\LeaguePrice;
use App\Mail\PurchaseConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.game_id' => 'required|exists:games,id',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        
        $order = DB::transaction(function () use ($validated, $user) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $game = Game::with('league')->find($item['game_id']);
                $quantity = $item['quantity'] ?? 1;
                
                // Calculate price based on day
                $isWeekend = Carbon::parse($game->game_datetime)->isWeekend();
                $leaguePrice = LeaguePrice::where('league_id', $game->league_id)->first();
                
                $unitPrice = 0;
                if ($leaguePrice) {
                    $unitPrice = $isWeekend ? $leaguePrice->weekend_price : $leaguePrice->weekday_price;
                }

                $totalPrice = $unitPrice * $quantity;
                $subtotal += $totalPrice;

                $itemsData[] = [
                    'game_id' => $game->id,
                    'description' => "{$game->home_team} vs {$game->away_team} - {$game->league->name}",
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            // Calculate tax (20%)
            $taxRate = 0.20;
            $tax = $subtotal * $taxRate;
            $total = $subtotal + $tax;

            // Create order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'confirmed',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'EUR',
                'payment_status' => 'unpaid',
            ]);

            // Create order items
            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            return $order;
        });

        // Send confirmation email
        try {
            Mail::to($user->email)->send(new PurchaseConfirmation($user, [
                'id' => $order->order_number,
                'date' => $order->created_at->format('d.m.Y H:i'),
                'items' => $order->items->map(fn($i) => ['name' => $i->description, 'price' => $i->total_price])->toArray(),
                'total' => $order->total,
            ]));
        } catch (\Exception $e) {
            \Log::error('Failed to send purchase confirmation: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order->load('items')
        ], 201);
    }

    public function myOrders(Request $request)
    {
        $orders = Order::with(['items.game.league'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $orders;
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $order->load(['items.game.league', 'invoice'])
        ]);
    }
}
