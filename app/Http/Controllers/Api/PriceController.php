<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\Game;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PriceController extends Controller
{
    public function index()
    {
        return Price::with('game')->where('is_active', true)->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_id' => 'required|exists:games,id',
            'price' => 'required|numeric|min:0',
            'day_type' => 'required|in:weekday,weekend',
            'is_active' => 'boolean'
        ]);

        return Price::create($validated);
    }

    public function show(Price $price)
    {
        return $price->load('game');
    }

    public function update(Request $request, Price $price)
    {
        $validated = $request->validate([
            'game_id' => 'exists:games,id',
            'price' => 'numeric|min:0',
            'day_type' => 'in:weekday,weekend',
            'is_active' => 'boolean'
        ]);

        $price->update($validated);
        return $price;
    }

    public function destroy(Price $price)
    {
        $price->delete();
        return response()->json(['message' => 'Price deleted successfully']);
    }

    public function getGamePrice(Game $game)
    {
        $gameDate = Carbon::parse($game->game_datetime);
        $dayType = $gameDate->isWeekend() ? 'weekend' : 'weekday';
        
        $price = $game->prices()
            ->where('day_type', $dayType)
            ->where('is_active', true)
            ->first();

        if (!$price) {
            return response()->json([
                'message' => 'No price found for this game',
                'suggested_day_type' => $dayType
            ], 404);
        }

        return $price;
    }
}