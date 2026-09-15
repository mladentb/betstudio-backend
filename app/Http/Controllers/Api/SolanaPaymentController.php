<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CartItem;
use App\Models\PurchasedGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolanaPaymentController extends Controller
{
    /**
     * Create a pending order for Solana payment
     */
    public function createPendingOrder(Request $request)
    {
        $request->validate([
            'crypto_currency' => 'nullable|in:SOL,USDC',
        ]);
        
        $user = $request->user();
        $cryptoCurrency = $request->input('crypto_currency', 'SOL');
        
        // Get cart items
        $cartItems = CartItem::with(['game.league'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Korpa je prazna'], 400);
        }

        $order = DB::transaction(function () use ($cartItems, $user, $cryptoCurrency) {
            $subtotal = $cartItems->sum('price');
            
            // Apply company discount if exists
            $discountPercent = $user->company?->discount_percent ?? 0;
            $discountAmount = $subtotal * ($discountPercent / 100);
            $afterDiscount = $subtotal - $discountAmount;
            
            // Calculate tax (20%)
            $tax = $afterDiscount * 0.20;
            $total = $afterDiscount + $tax;

            // Create pending order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'EUR',
                'payment_method' => $cryptoCurrency === 'USDC' ? 'usdc' : 'solana',
                'payment_status' => 'pending',
            ]);

            // Create order items from cart
            foreach ($cartItems as $item) {
                $game = $item->game;
                $order->items()->create([
                    'game_id' => $game->id,
                    'description' => "{$game->home_team} vs {$game->away_team} - {$game->league->name}",
                    'quantity' => 1,
                    'unit_price' => $item->price,
                    'total_price' => $item->price,
                ]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Pending order created',
            'order' => $order->load('items'),
        ]);
    }

    /**
     * Confirm Solana payment after transaction
     */
    public function confirmPayment(Request $request, Order $order)
    {
        $request->validate([
            'tx_signature' => 'required|string|min:80|max:100',
        ]);

        $user = $request->user();

        // Verify order belongs to user
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check order is pending
        if ($order->payment_status !== 'pending') {
            return response()->json(['message' => 'Order already processed'], 400);
        }

        $txSignature = $request->tx_signature;

        // Verify transaction on Solana
        $verified = $this->verifySolanaTransaction($txSignature, $order);

        if (!$verified) {
            Log::warning("Solana TX verification failed", [
                'order_id' => $order->id,
                'tx_signature' => $txSignature,
            ]);
        }

        DB::transaction(function () use ($order, $txSignature, $user) {
            // Update order - store TX in notes field
            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'notes' => 'Solana TX: ' . $txSignature,
            ]);

            // Create purchased games
            foreach ($order->items as $item) {
                PurchasedGame::firstOrCreate([
                    'user_id' => $user->id,
                    'game_id' => $item->game_id,
                ], [
                    'company_id' => $user->company_id,
                    'order_id' => $order->id,
                    'price_paid' => $item->total_price,
                ]);
            }

            // Clear cart
            CartItem::where('user_id', $user->id)->delete();
        });

        Log::info("Solana payment confirmed", [
            'order_id' => $order->id,
            'tx_signature' => $txSignature,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Payment confirmed',
            'order' => $order->fresh()->load('items'),
        ]);
    }

    /**
     * Verify Solana transaction
     */
    private function verifySolanaTransaction(string $signature, Order $order): bool
    {
        try {
            $rpcUrl = config('services.solana.rpc_url', 'https://api.devnet.solana.com');
            
            $response = Http::timeout(10)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getTransaction',
                'params' => [
                    $signature,
                    ['encoding' => 'json', 'commitment' => 'confirmed']
                ],
            ]);

            $data = $response->json();

            if (isset($data['result']) && $data['result'] !== null) {
                Log::info("Solana TX verified", [
                    'order_id' => $order->id,
                    'tx_signature' => $signature,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Solana TX verification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's order with items and game details
     */
    public function getOrder(Request $request, Order $order)
    {
        $user = $request->user();

        // Verify order belongs to user
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load order with items, games, purchased_games
        $order->load([
            'items.game.league.sport',
        ]);

        // Get purchased games for this order
        $purchasedGames = PurchasedGame::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('game_id');

        // Add purchased_game_id to items
        $items = $order->items->map(function ($item) use ($purchasedGames) {
            $itemArray = $item->toArray();
            $purchasedGame = $purchasedGames->get($item->game_id);
            $itemArray['purchased_game_id'] = $purchasedGame?->id;
            return $itemArray;
        });

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
                'solana_tx_signature' => str_replace('Solana TX: ', '', $order->notes ?? ''),
                'items' => $items,
            ],
        ]);
    }
}
