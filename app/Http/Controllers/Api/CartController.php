<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Game;
use App\Models\LeaguePrice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchasedGame;
use App\Models\Company;
use App\Mail\PurchaseConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = CartItem::with(['game.league.sport'])
            ->where('user_id', $user->id)
            ->get();

        $subtotal = $items->sum('price');
        
        // Get user's company discount
        $discountPercent = 0;
        if ($user->company_id) {
            $company = Company::find($user->company_id);
            $discountPercent = $company ? (float)$company->discount_percent : 0;
        }
        
        $discountAmount = $subtotal * ($discountPercent / 100);
        $afterDiscount = $subtotal - $discountAmount;
        $tax = $afterDiscount * 0.20;
        $total = $afterDiscount + $tax;

        return response()->json([
            'data' => $items,
            'subtotal' => round($subtotal, 2),
            'discount_percent' => $discountPercent,
            'discount_amount' => round($discountAmount, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'count' => $items->count(),
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'game_id' => 'required|exists:games,id'
        ]);

        $user = $request->user();
        $game = Game::with('league')->findOrFail($validated['game_id']);

        // Check if already in cart
        $existing = CartItem::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Već je u korpi'], 400);
        }

        // Check if already purchased (only PAID orders count)
        $purchased = PurchasedGame::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->whereHas('order', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->first();

        if ($purchased) {
            return response()->json(['message' => 'Već ste kupili ovaj meč'], 400);
        }

        // Check if there's a pending order with this game (to avoid duplicates)
        $pendingOrder = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('items', function ($q) use ($game) {
                $q->where('game_id', $game->id);
            })
            ->first();

        if ($pendingOrder) {
            return response()->json([
                'message' => 'Ovaj meč je već u porudžbini koja čeka uplatu',
                'order_number' => $pendingOrder->order_number
            ], 400);
        }

        $price = $this->calculatePrice($game);

        $item = CartItem::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'price' => $price,
            'created_at' => now()
        ]);

        return response()->json([
            'message' => 'Dodato u korpu',
            'data' => $item->load('game.league.sport')
        ], 201);
    }

    public function remove(Request $request, $gameId)
    {
        CartItem::where('user_id', $request->user()->id)
            ->where('game_id', $gameId)
            ->delete();

        return response()->json(['message' => 'Uklonjeno iz korpe']);
    }

    public function clear(Request $request)
    {
        CartItem::where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Korpa ispražnjena']);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:bank_transfer,card,crypto',
        ]);

        $user = $request->user();
        $cartItems = CartItem::with(['game.league'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Korpa je prazna'], 400);
        }

        $order = DB::transaction(function () use ($user, $cartItems, $validated) {
            $subtotal = $cartItems->sum('price');
            
            // Get user's company discount
            $discountPercent = 0;
            if ($user->company_id) {
                $company = Company::find($user->company_id);
                $discountPercent = $company ? (float)$company->discount_percent : 0;
            }
            
            $discountAmount = $subtotal * ($discountPercent / 100);
            $afterDiscount = $subtotal - $discountAmount;
            $tax = $afterDiscount * 0.20;
            $total = $afterDiscount + $tax;

            // Create order with pending status - NOT confirmed until payment is verified
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'pending', // Changed from 'confirmed' - wait for payment
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'EUR',
                'payment_status' => 'pending', // Changed from 'unpaid'
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'game_id' => $item->game_id,
                    'description' => $item->game->home_team . ' vs ' . $item->game->away_team,
                    'quantity' => 1,
                    'unit_price' => $item->price,
                    'total_price' => $item->price,
                ]);

                // DO NOT create PurchasedGame here!
                // PurchasedGame is created only when payment is confirmed by admin
            }

            CartItem::where('user_id', $user->id)->delete();

            return $order;
        });

        // Send order confirmation email (not purchase confirmation - payment pending)
        try {
            Mail::to($user->email)->send(new PurchaseConfirmation($user, [
                'id' => $order->order_number,
                'date' => $order->created_at->format('d.m.Y H:i'),
                'items' => $order->items->map(fn($i) => ['name' => $i->description, 'price' => '€' . $i->total_price]),
                'discount' => $order->discount_amount > 0 ? "-€{$order->discount_amount} ({$order->discount_percent}%)" : null,
                'total' => '€' . $order->total,
                'payment_method' => $validated['payment_method'],
                'status' => 'Čeka uplatu',
            ]));
        } catch (\Exception $e) {
            \Log::error('Failed to send order email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Porudžbina kreirana - čeka se uplata',
            'order' => $order->load('items'),
        ], 201);
    }

    private function calculatePrice(Game $game): float
    {
        $leaguePrice = LeaguePrice::where('league_id', $game->league_id)->first();
        
        if (!$leaguePrice) {
            return 10.00;
        }

        $gameDate = Carbon::parse($game->game_datetime);
        $isWeekend = $gameDate->isWeekend();

        return $isWeekend ? (float)$leaguePrice->weekend_price : (float)$leaguePrice->weekday_price;
    }
}
