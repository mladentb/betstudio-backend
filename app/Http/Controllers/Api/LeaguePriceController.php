<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\LeaguePrice;
use Illuminate\Http\Request;

class LeaguePriceController extends Controller
{
    public function index(Request $request)
    {
        $query = League::with(['sport', 'price']);

        if ($request->filled('sport_id')) {
            $query->where('sport_id', $request->sport_id);
        }

        $leagues = $query->orderBy('sport_id')->orderBy('name')->get();

        return response()->json([
            'data' => $leagues->map(function ($league) {
                return [
                    'id' => $league->id,
                    'name' => $league->name,
                    'sport' => $league->sport,
                    'weekday_price' => $league->price?->weekday_price ?? 0,
                    'weekend_price' => $league->price?->weekend_price ?? 0,
                    'currency' => $league->price?->currency ?? 'EUR',
                    'is_active' => $league->price?->is_active ?? true,
                    'has_price' => $league->price !== null,
                ];
            })
        ]);
    }

    public function update(Request $request, League $league)
    {
        $validated = $request->validate([
            'weekday_price' => 'required|numeric|min:0',
            'weekend_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'is_active' => 'nullable|boolean',
        ]);

        $price = LeaguePrice::updateOrCreate(
            ['league_id' => $league->id],
            [
                'weekday_price' => $validated['weekday_price'],
                'weekend_price' => $validated['weekend_price'],
                'currency' => $validated['currency'] ?? 'EUR',
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'message' => 'Price updated',
            'data' => $price->load('league')
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*.league_id' => 'required|exists:leagues,id',
            'prices.*.weekday_price' => 'required|numeric|min:0',
            'prices.*.weekend_price' => 'required|numeric|min:0',
        ]);

        foreach ($validated['prices'] as $priceData) {
            LeaguePrice::updateOrCreate(
                ['league_id' => $priceData['league_id']],
                [
                    'weekday_price' => $priceData['weekday_price'],
                    'weekend_price' => $priceData['weekend_price'],
                    'currency' => $priceData['currency'] ?? 'EUR',
                    'is_active' => $priceData['is_active'] ?? true,
                ]
            );
        }

        return response()->json(['message' => 'Prices updated']);
    }
}
