<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Sport;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    /**
     * GET /api/leagues
     * 
     * Query params:
     * - sport_id: filter by sport
     * - gender: male|female
     * - search: search by name
     * - is_active: true|false
     * - with_games_count: include games count
     * - sort_by: name|created_at (default: name)
     * - sort_dir: asc|desc (default: asc)
     * - per_page: items per page (default: 15, 0 for all)
     */
    public function index(Request $request)
    {
        $query = League::with('sport');

        // Filter by active status (default: only active, 'all' for all)
        if ($request->get('is_active') === 'all') {
            // Don't filter - show all
        } elseif ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_active', true);
        }

        // Filter by sport
        if ($request->filled('sport_id')) {
            $query->where('sport_id', $request->sport_id);
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Include games count
        if ($request->boolean('with_games_count')) {
            $query->withCount(['games' => function ($q) {
                $q->where('is_available_for_sale', true);
            }]);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $allowedSorts = ['name', 'created_at', 'games_count'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        // Pagination (0 = return all)
        $perPage = $request->get('per_page', 15);
        
        if ($perPage == 0) {
            return response()->json([
                'data' => $query->get(),
                'meta' => ['total' => $query->count()]
            ]);
        }

        return $query->paginate(min($perPage, 100))->withQueryString();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sport_id' => 'required|exists:sports,id',
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'required|string|unique:leagues',
            'api_league_id' => 'nullable|string',
            'api_endpoint' => 'nullable|string',
            'api_source' => 'nullable|string|max:50',
            'api_token' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
            'country_code' => 'nullable|string|max:3',
            'country_name' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $league = League::create($validated);

        return response()->json([
            'message' => 'League created successfully',
            'data' => $league->load('sport')
        ], 201);
    }

    public function show(League $league)
    {
        $league->loadCount(['games' => function ($q) {
            $q->where('is_available_for_sale', true);
        }]);

        return response()->json([
            'data' => $league->load('sport')
        ]);
    }

    public function update(Request $request, League $league)
    {
        $validated = $request->validate([
            'sport_id' => 'exists:sports,id',
            'name' => 'string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'string|unique:leagues,slug,' . $league->id,
            'api_league_id' => 'nullable|string',
            'api_endpoint' => 'nullable|string',
            'api_source' => 'nullable|string|max:50',
            'api_token' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
            'country_code' => 'nullable|string|max:3',
            'country_name' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $league->update($validated);

        return response()->json([
            'message' => 'League updated successfully',
            'data' => $league->fresh()->load('sport')
        ]);
    }

    public function destroy(League $league)
    {
        $league->delete();
        return response()->json(['message' => 'League deleted successfully']);
    }

    /**
     * GET /api/sports/{sport}/leagues
     */
    public function bySport(Request $request, Sport $sport)
    {
        $query = $sport->leagues()->where('is_active', true);

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->boolean('with_games_count')) {
            $query->withCount(['games' => function ($q) {
                $q->where('is_available_for_sale', true);
            }]);
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
            'meta' => [
                'sport' => $sport->name,
                'total' => $query->count()
            ]
        ]);
    }
}
