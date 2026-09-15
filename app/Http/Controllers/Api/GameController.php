<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\League;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GameController extends Controller
{
    /**
     * GET /api/games
     * 
     * Query params:
     * - league_id: filter by league
     * - sport_id: filter by sport
     * - status: scheduled|live|finished|cancelled
     * - date_from: filter games from date
     * - date_to: filter games until date
     * - search: search home/away team names
     * - sort_by: game_datetime|home_team|created_at (default: game_datetime)
     * - sort_dir: asc|desc (default: asc)
     * - per_page: items per page (default: 15)
     */
    public function index(Request $request)
    {
        $query = Game::with('league.sport', 'prices')
            ->where('is_available_for_sale', true);

        // Filter by league
        if ($request->filled('league_id')) {
            $query->where('league_id', $request->league_id);
        }

        // Filter by sport (through league)
        if ($request->filled('sport_id')) {
            $query->whereHas('league', function ($q) use ($request) {
                $q->where('sport_id', $request->sport_id);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('game_datetime', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('game_datetime', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Search in team names
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('home_team', 'like', "%{$search}%")
                  ->orWhere('away_team', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'game_datetime');
        $sortDir = $request->get('sort_dir', 'asc');
        $allowedSorts = ['game_datetime', 'home_team', 'away_team', 'created_at', 'status'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Max 100 items

        return $query->paginate($perPage)->withQueryString();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'league_id' => 'required|exists:leagues,id',
            'api_game_id' => 'required|string|unique:games',
            'home_team' => 'required|string',
            'away_team' => 'required|string',
            'game_datetime' => 'required|date',
            'venue' => 'nullable|string',
            'status' => 'in:scheduled,live,finished,cancelled',
            'home_score' => 'nullable|integer',
            'away_score' => 'nullable|integer',
            'is_available_for_sale' => 'boolean'
        ]);

        $game = Game::create($validated);

        return response()->json([
            'message' => 'Game created successfully',
            'data' => $game->load('league.sport')
        ], 201);
    }

    public function show(Game $game)
    {
        return response()->json([
            'data' => $game->load('league.sport', 'prices')
        ]);
    }

    public function update(Request $request, Game $game)
    {
        $validated = $request->validate([
            'league_id' => 'exists:leagues,id',
            'api_game_id' => 'string|unique:games,api_game_id,' . $game->id,
            'home_team' => 'string',
            'away_team' => 'string',
            'game_datetime' => 'date',
            'venue' => 'nullable|string',
            'status' => 'in:scheduled,live,finished,cancelled',
            'home_score' => 'nullable|integer',
            'away_score' => 'nullable|integer',
            'live_data' => 'nullable|json',
            'is_available_for_sale' => 'boolean'
        ]);

        $game->update($validated);

        return response()->json([
            'message' => 'Game updated successfully',
            'data' => $game->fresh()->load('league.sport', 'prices')
        ]);
    }

    public function destroy(Game $game)
    {
        $game->delete();
        return response()->json(['message' => 'Game deleted successfully']);
    }

    /**
     * GET /api/leagues/{league}/games
     */
    public function byLeague(Request $request, League $league)
    {
        $query = $league->games()
            ->with(['league.sport', 'prices'])
            ->where('is_available_for_sale', true);

        // Apply same filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('game_datetime', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('game_datetime', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $sortBy = $request->get('sort', 'game_datetime');
        $sortDir = $request->get('direction', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 100), 100);

        return response()->json([
            'data' => $query->paginate($perPage)->items(),
            'league' => $league->load('sport'),
            'meta' => [
                'total' => $league->games()->where('is_available_for_sale', true)->count()
            ]
        ]);
    }

    /**
     * GET /api/games/upcoming
     */
    public function upcoming(Request $request)
    {
        $limit = min($request->get('limit', 20), 50);

        $games = Game::with('league.sport', 'prices')
            ->where('game_datetime', '>', Carbon::now())
            ->where('status', 'scheduled')
            ->where('is_available_for_sale', true)
            ->orderBy('game_datetime', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'total' => $games->count(),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * GET /api/games/live
     */
    public function live()
    {
        $games = Game::with('league.sport', 'prices')
            ->where('status', 'live')
            ->where('is_available_for_sale', true)
            ->orderBy('game_datetime', 'asc')
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'total' => $games->count(),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * GET /api/games/today
     */
    public function today()
    {
        $games = Game::with('league.sport', 'prices')
            ->whereDate('game_datetime', Carbon::today())
            ->where('is_available_for_sale', true)
            ->orderBy('game_datetime', 'asc')
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'total' => $games->count(),
                'date' => Carbon::today()->toDateString(),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }
}
