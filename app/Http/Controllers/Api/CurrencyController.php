<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Dobij listu valuta i trenutne kurseve
     */
    public function index()
    {
        $rates = $this->currencyService->getRates();
        $currencies = $this->currencyService->getCurrencies();

        return response()->json([
            'currencies' => collect($currencies)->map(fn($c, $code) => [
                'code' => $code,
                'symbol' => $c['symbol'],
                'name' => $c['name'],
                'rate' => $rates[$code] ?? 1,
            ])->values(),
            'base' => 'EUR',
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Konvertuj iznos
     */
    public function convert(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|in:EUR,USD,SOL',
            'to' => 'required|string|in:EUR,USD,SOL',
        ]);

        $amountEur = $this->currencyService->convertToEur($validated['amount'], $validated['from']);
        $converted = $this->currencyService->convertFromEur($amountEur, $validated['to']);

        return response()->json([
            'original' => [
                'amount' => $validated['amount'],
                'currency' => $validated['from'],
            ],
            'converted' => [
                'amount' => $converted,
                'currency' => $validated['to'],
                'formatted' => $this->currencyService->format($converted, $validated['to']),
            ],
        ]);
    }
}
