<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use App\Models\League;
use App\Models\Game;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Info(
 *     title="BetStudio Public API",
 *     version="1.0.0",
 *     description="API za pristup sportskim podacima, rasporedima mečeva i live rezultatima",
 *     @OA\Contact(
 *         email="api@betstudio.com",
 *         name="BetStudio API Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://127.0.0.1:8000/api/v1",
 *     description="Development server"
 * )
 * 
 * @OA\Server(
 *     url="https://api.betstudio.com/v1",
 *     description="Production server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="apiKey",
 *     type="apiKey",
 *     in="header",
 *     name="X-API-Key"
 * )
 */
class PublicApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/sports",
     *     summary="Lista svih sportova",
     *     tags={"Sports"},
     *     security={{"apiKey":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Uspešno",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="leagues_count", type="integer"),
     *                 @OA\Property(property="games_count", type="integer")
     *             ))
     *         )
     *     )
     * )
     */
    public function sports(Request $request)
    {
        $sports = Cache::remember('public_api_sports', 300, function () {
            return Sport::withCount('leagues')
                ->orderBy('name')
                ->get()
                ->map(function ($s) {
                    // Count games through leagues
                    $gamesCount = Game::whereHas('league', fn($q) => $q->where('sport_id', $s->id))->count();
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'slug' => $s->slug,
                        'leagues_count' => $s->leagues_count,
                        'games_count' => $gamesCount,
                    ];
                });
        });

        return response()->json([
            'data' => $sports,
            'meta' => [
                'total' => $sports->count(),
                'cached_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/leagues",
     *     summary="Lista svih liga",
     *     tags={"Leagues"},
     *     security={{"apiKey":{}}},
     *     @OA\Parameter(
     *         name="sport_id",
     *         in="query",
     *         description="Filter po sportu",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function leagues(Request $request)
    {
        $cacheKey = 'public_api_leagues_' . ($request->sport_id ?? 'all');
        
        $leagues = Cache::remember($cacheKey, 300, function () use ($request) {
            $query = League::with('sport')->withCount('games');
            
            if ($request->sport_id) {
                $query->where('sport_id', $request->sport_id);
            }
            
            return $query->orderBy('name')->get()->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'sport' => [
                    'id' => $l->sport->id,
                    'name' => $l->sport->name,
                ],
                'country' => $l->country,
                'games_count' => $l->games_count,
            ]);
        });

        return response()->json([
            'data' => $leagues,
            'meta' => [
                'total' => $leagues->count(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/games",
     *     summary="Lista mečeva",
     *     tags={"Games"},
     *     security={{"apiKey":{}}},
     *     @OA\Parameter(name="sport_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="league_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", description="Format: YYYY-MM-DD", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="scheduled, live, finished", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=50)),
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function games(Request $request)
    {
        $query = Game::with(['league.sport'])
            ->select([
                'id', 'league_id', 'home_team', 'away_team', 
                'game_datetime', 'status', 'home_score', 'away_score',
                'venue', 'round', 'created_at', 'updated_at'
            ]);

        // Filters
        if ($request->sport_id) {
            $query->whereHas('league', fn($q) => $q->where('sport_id', $request->sport_id));
        }

        if ($request->league_id) {
            $query->where('league_id', $request->league_id);
        }

        if ($request->date) {
            $query->whereDate('game_datetime', $request->date);
        }

        if ($request->date_from) {
            $query->whereDate('game_datetime', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('game_datetime', '<=', $request->date_to);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->per_page ?? 50, 100);
        $games = $query->orderBy('game_datetime')->paginate($perPage);

        return response()->json([
            'data' => $games->items(),
            'meta' => [
                'current_page' => $games->currentPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
                'last_page' => $games->lastPage(),
            ],
            'links' => [
                'first' => $games->url(1),
                'last' => $games->url($games->lastPage()),
                'prev' => $games->previousPageUrl(),
                'next' => $games->nextPageUrl(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/games/{id}",
     *     summary="Detalji meča",
     *     tags={"Games"},
     *     security={{"apiKey":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Uspešno"),
     *     @OA\Response(response=404, description="Meč nije pronađen")
     * )
     */
    public function game(Request $request, $id)
    {
        $game = Game::with(['league.sport'])->find($id);

        if (!$game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $game->id,
                'home_team' => $game->home_team,
                'away_team' => $game->away_team,
                'game_datetime' => $game->game_datetime,
                'status' => $game->status,
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'venue' => $game->venue,
                'round' => $game->round,
                'league' => [
                    'id' => $game->league->id,
                    'name' => $game->league->name,
                ],
                'sport' => [
                    'id' => $game->league->sport->id,
                    'name' => $game->league->sport->name,
                ],
                'updated_at' => $game->updated_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/games/live",
     *     summary="Mečevi uživo",
     *     tags={"Games"},
     *     security={{"apiKey":{}}},
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function liveGames(Request $request)
    {
        $games = Game::with(['league.sport'])
            ->where('status', 'live')
            ->orderBy('game_datetime')
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'total' => $games->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/games/today",
     *     summary="Današnji mečevi",
     *     tags={"Games"},
     *     security={{"apiKey":{}}},
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function todayGames(Request $request)
    {
        $games = Game::with(['league.sport'])
            ->whereDate('game_datetime', today())
            ->orderBy('game_datetime')
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'date' => today()->toDateString(),
                'total' => $games->count(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/games/upcoming",
     *     summary="Predstojeći mečevi (narednih 7 dana)",
     *     tags={"Games"},
     *     security={{"apiKey":{}}},
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", default=7)),
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function upcomingGames(Request $request)
    {
        $days = min($request->days ?? 7, 30);
        
        $games = Game::with(['league.sport'])
            ->where('game_datetime', '>=', now())
            ->where('game_datetime', '<=', now()->addDays($days))
            ->where('status', 'scheduled')
            ->orderBy('game_datetime')
            ->get();

        return response()->json([
            'data' => $games,
            'meta' => [
                'days' => $days,
                'total' => $games->count(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/stats/overview",
     *     summary="Statistički pregled",
     *     tags={"Statistics"},
     *     security={{"apiKey":{}}},
     *     @OA\Response(response=200, description="Uspešno")
     * )
     */
    public function statsOverview(Request $request)
    {
        $stats = Cache::remember('public_api_stats', 60, function () {
            return [
                'sports_count' => Sport::count(),
                'leagues_count' => League::count(),
                'total_games' => Game::count(),
                'live_games' => Game::where('status', 'live')->count(),
                'today_games' => Game::whereDate('game_datetime', today())->count(),
                'upcoming_games' => Game::where('game_datetime', '>=', now())
                    ->where('status', 'scheduled')->count(),
            ];
        });

        return response()->json([
            'data' => $stats,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ]
        ]);
    }
}
