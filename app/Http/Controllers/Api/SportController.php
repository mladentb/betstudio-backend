<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use Illuminate\Http\Request;

class SportController extends Controller
{
    /**
     * GET /api/sports
     * 
     * Query params:
     * - search: search by name
     * - is_active: true|false (default: true)
     * - with_leagues: include leagues
     * - with_counts: include leagues and games counts
     * - sort_by: name|created_at (default: name)
     * - sort_dir: asc|desc (default: asc)
     */
    public function index(Request $request)
    {
        $query = Sport::query();

        // Filter by active status (default: only active)
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_active', true);
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Include leagues
        if ($request->boolean('with_leagues')) {
            $query->with(['leagues' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }]);
        }

        // Include counts
        if ($request->boolean('with_counts')) {
            $query->withCount([
                'leagues' => function ($q) {
                    $q->where('is_active', true);
                }
            ]);
            
            // Also count games through leagues
            $query->addSelect([
                'games_count' => \App\Models\Game::selectRaw('count(*)')
                    ->join('leagues', 'games.league_id', '=', 'leagues.id')
                    ->whereColumn('leagues.sport_id', 'sports.id')
                    ->where('games.is_available_for_sale', true)
            ]);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $allowedSorts = ['name', 'created_at'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        // No pagination for sports (usually small dataset)
        return response()->json([
            'data' => $query->get(),
            'meta' => [
                'total' => $query->count(),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:sports',
            'api_source' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $sport = Sport::create($validated);

        return response()->json([
            'message' => 'Sport created successfully',
            'data' => $sport
        ], 201);
    }

    public function show(Sport $sport)
    {
        $sport->load(['leagues' => function ($q) {
            $q->where('is_active', true)
              ->withCount(['games' => function ($q) {
                  $q->where('is_available_for_sale', true);
              }]);
        }]);

        return response()->json([
            'data' => $sport
        ]);
    }

    public function update(Request $request, Sport $sport)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|unique:sports,slug,' . $sport->id,
            'api_source' => 'string',
            'is_active' => 'boolean'
        ]);

        $sport->update($validated);

        return response()->json([
            'message' => 'Sport updated successfully',
            'data' => $sport
        ]);
    }

    public function destroy(Sport $sport)
    {
        $sport->delete();
        return response()->json(['message' => 'Sport deleted successfully']);
    }
}
