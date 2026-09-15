<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasedGame;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminStreamingController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchasedGame::with(['user:id,name,email,company_name', 'game.league.sport']);

        // Filter only with URL (when checkbox is checked)
        if ($request->get('only_with_url') === '1') {
            $query->whereNotNull('stream_type')
                  ->where('stream_type', '!=', '')
                  ->whereNotNull('stream_url')
                  ->where('stream_url', '!=', '');
        } else {
            $query->whereNotNull('stream_type')
                  ->where('stream_type', '!=', '');
        }

        // Filter only future games (default)
        if ($request->get('only_future') === '1') {
            $query->whereHas('game', fn($q) => $q->where('game_datetime', '>=', Carbon::now()));
        }

        if ($request->filled('stream_type')) {
            $query->where('stream_type', $request->stream_type);
        }

        if ($request->filled('game_status')) {
            $query->whereHas('game', fn($q) => $q->where('status', $request->game_status));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', fn($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                  ->orWhereHas('game', fn($q) => $q->where('home_team', 'ilike', "%{$search}%")->orWhere('away_team', 'ilike', "%{$search}%"));
            });
        }

        // Order by game datetime ascending (nearest first)
        $query->orderBy(
            PurchasedGame::select('game_datetime')
                ->from('games')
                ->whereColumn('games.id', 'purchased_games.game_id')
                ->limit(1),
            'asc'
        );

        $destinations = $query->get()->map(fn($pg) => [
            'id' => $pg->id,
            'user' => ['name' => $pg->user->name, 'email' => $pg->user->email, 'company' => $pg->user->company_name],
            'game' => [
                'home_team' => $pg->game->home_team,
                'away_team' => $pg->game->away_team,
                'datetime' => $pg->game->game_datetime,
                'status' => $pg->game->status,
                'league' => $pg->game->league?->name,
                'sport_slug' => $pg->game->league?->sport?->slug,
            ],
            'stream_type' => $pg->stream_type,
            'stream_url' => $pg->stream_url,
            'stream_key' => $pg->stream_key,
            'updated_at' => $pg->updated_at,
        ]);

        // Stats - only future games with URL
        $futureWithUrl = PurchasedGame::whereNotNull('stream_type')
            ->where('stream_type', '!=', '')
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->whereHas('game', fn($q) => $q->where('game_datetime', '>=', Carbon::now()))
            ->count();

        $stats = [
            'total' => PurchasedGame::whereNotNull('stream_type')->where('stream_type', '!=', '')->count(),
            'with_url' => $futureWithUrl,
            'rtmp' => PurchasedGame::where('stream_type', 'rtmp')
                ->whereNotNull('stream_url')->where('stream_url', '!=', '')
                ->whereHas('game', fn($q) => $q->where('game_datetime', '>=', Carbon::now()))
                ->count(),
            'srt' => PurchasedGame::where('stream_type', 'srt')
                ->whereNotNull('stream_url')->where('stream_url', '!=', '')
                ->whereHas('game', fn($q) => $q->where('game_datetime', '>=', Carbon::now()))
                ->count(),
        ];

        return response()->json(['data' => $destinations, 'stats' => $stats]);
    }
}
