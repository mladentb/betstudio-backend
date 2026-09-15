<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasedGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NFTController extends Controller
{
    /**
     * Update purchased game with NFT mint info
     */
    public function updateNFTInfo(Request $request, PurchasedGame $purchasedGame)
    {
        $validated = $request->validate([
            'nft_mint_address' => 'required|string|max:50',
            'nft_metadata_uri' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Verify ownership
        if ($purchasedGame->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update with NFT info
        $purchasedGame->update([
            'nft_mint_address' => $validated['nft_mint_address'],
            'nft_metadata_uri' => $validated['nft_metadata_uri'] ?? null,
            'is_nft' => true,
        ]);

        Log::info('NFT minted for purchased game', [
            'purchased_game_id' => $purchasedGame->id,
            'user_id' => $user->id,
            'nft_mint_address' => $validated['nft_mint_address'],
        ]);

        return response()->json([
            'message' => 'NFT info updated',
            'purchased_game' => $purchasedGame->fresh()->load('game.league'),
        ]);
    }

    /**
     * Verify NFT ownership for stream access
     */
    public function verifyAccess(Request $request, PurchasedGame $purchasedGame)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|max:50',
        ]);

        $user = $request->user();

        // If NFT-based, we need to verify on-chain ownership
        // For now, we just check if the wallet matches
        if ($purchasedGame->is_nft) {
            $userWallet = $user->wallet_address;
            
            if ($userWallet !== $validated['wallet_address']) {
                return response()->json([
                    'has_access' => false,
                    'message' => 'Wallet address does not match',
                ]);
            }

            // TODO: Add actual on-chain verification via RPC
            // For now, we trust the frontend verification
            
            return response()->json([
                'has_access' => true,
                'nft_mint_address' => $purchasedGame->nft_mint_address,
            ]);
        }

        // Traditional ownership check
        if ($purchasedGame->user_id === $user->id) {
            return response()->json([
                'has_access' => true,
            ]);
        }

        return response()->json([
            'has_access' => false,
            'message' => 'No access to this stream',
        ]);
    }

    /**
     * Get all NFT access passes for user
     */
    public function myNFTs(Request $request)
    {
        $user = $request->user();

        $nfts = PurchasedGame::with(['game.league.sport'])
            ->where('user_id', $user->id)
            ->where('is_nft', true)
            ->whereNotNull('nft_mint_address')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $nfts,
        ]);
    }

    /**
     * Transfer NFT to new owner (after on-chain transfer)
     */
    public function recordTransfer(Request $request, PurchasedGame $purchasedGame)
    {
        $validated = $request->validate([
            'new_owner_wallet' => 'required|string|max:50',
            'tx_signature' => 'required|string|max:100',
        ]);

        // Find user by wallet address
        $newOwner = \App\Models\User::where('wallet_address', $validated['new_owner_wallet'])->first();

        if (!$newOwner) {
            // Create record without user_id for external wallets
            Log::info('NFT transferred to external wallet', [
                'purchased_game_id' => $purchasedGame->id,
                'new_wallet' => $validated['new_owner_wallet'],
                'tx_signature' => $validated['tx_signature'],
            ]);

            return response()->json([
                'message' => 'Transfer recorded (external wallet)',
                'note' => 'New owner must register and connect wallet to access stream',
            ]);
        }

        // Update ownership
        $oldUserId = $purchasedGame->user_id;
        $purchasedGame->update([
            'user_id' => $newOwner->id,
        ]);

        Log::info('NFT ownership transferred', [
            'purchased_game_id' => $purchasedGame->id,
            'old_user_id' => $oldUserId,
            'new_user_id' => $newOwner->id,
            'tx_signature' => $validated['tx_signature'],
        ]);

        return response()->json([
            'message' => 'Ownership transferred successfully',
            'new_owner' => $newOwner->name,
        ]);
    }
}
