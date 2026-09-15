<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasedGame;
use Illuminate\Http\Request;

class MyGamesController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchasedGame::with(['game.league.sport', 'order'])
            ->where('user_id', $request->user()->id);

        // Filter: upcoming, live, finished
        if ($request->filled('filter')) {
            $now = now();
            switch ($request->filter) {
                case 'upcoming':
                    $query->whereHas('game', fn($q) => $q->where('game_datetime', '>', $now));
                    break;
                case 'live':
                    $query->whereHas('game', fn($q) => $q->where('status', 'live'));
                    break;
                case 'finished':
                    $query->whereHas('game', fn($q) => $q->where('status', 'finished'));
                    break;
            }
        }

        $games = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $games]);
    }

    public function show(Request $request, PurchasedGame $purchasedGame)
    {
        if ($purchasedGame->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $purchasedGame->load(['game.league.sport', 'order'])
                ->makeVisible(['stream_key', 'srt_passphrase'])
        ]);
    }

    public function updateStreamSettings(Request $request, PurchasedGame $purchasedGame)
    {
        if ($purchasedGame->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if game is in the future
        if ($purchasedGame->game->game_datetime < now()) {
            return response()->json(['message' => 'Nije moguće menjati podešavanja za prošle mečeve'], 400);
        }

        $validated = $request->validate([
            'stream_type' => 'required|in:rtmp,srt',
            'stream_url' => 'nullable|string|max:500',
            'stream_key' => 'nullable|string|max:255',
            'srt_url' => 'nullable|string|max:500',
            'srt_passphrase' => 'nullable|string|max:100',
        ]);

        $purchasedGame->update($validated);

        return response()->json([
            'message' => 'Podešavanja sačuvana',
            'data' => $purchasedGame->fresh()->makeVisible(['stream_key', 'srt_passphrase'])
        ]);
    }

    // Public stats endpoint (no auth required)
    public function publicStats($token)
    {
        $purchasedGame = PurchasedGame::with(['game.league.sport'])
            ->where('stats_token', $token)
            ->firstOrFail();

        $game = $purchasedGame->game;

        return response()->json([
            'data' => [
                'home_team' => $game->home_team,
                'away_team' => $game->away_team,
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'status' => $game->status,
                'game_datetime' => $game->game_datetime,
                'league' => $game->league->name,
                'sport' => $game->league->sport->name,
                'venue' => $game->venue,
            ]
        ]);
    }
}
