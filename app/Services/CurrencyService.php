<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    // Podržane valute
    const CURRENCIES = [
        'EUR' => ['symbol' => '€', 'name' => 'Euro'],
        'USD' => ['symbol' => '$', 'name' => 'US Dollar'],
        'SOL' => ['symbol' => '◎', 'name' => 'Solana'],
    ];

    /**
     * Dobij trenutne kurseve prema EUR
     */
    public function getRates(): array
    {
        return Cache::remember('currency_rates', 300, function () {
            $rates = ['EUR' => 1.0];

            // USD kurs iz ECB ili fallback
            try {
                $response = Http::timeout(5)->get('https://api.exchangerate-api.com/v4/latest/EUR');
                if ($response->successful()) {
                    $data = $response->json();
                    $rates['USD'] = $data['rates']['USD'] ?? 1.08;
                }
            } catch (\Exception $e) {
                $rates['USD'] = 1.08; // Fallback
            }

            // SOL kurs iz CoinGecko
            try {
                $response = Http::timeout(5)->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'solana',
                    'vs_currencies' => 'eur'
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    $solInEur = $data['solana']['eur'] ?? 150;
                    $rates['SOL'] = 1 / $solInEur; // Koliko SOL za 1 EUR
                }
            } catch (\Exception $e) {
                $rates['SOL'] = 1 / 150; // Fallback ~150 EUR po SOL
            }

            return $rates;
        });
    }

    /**
     * Konvertuj iznos iz EUR u drugu valutu
     */
    public function convertFromEur(float $amountEur, string $toCurrency): float
    {
        if ($toCurrency === 'EUR') {
            return $amountEur;
        }

        $rates = $this->getRates();
        $rate = $rates[$toCurrency] ?? 1;

        return round($amountEur * $rate, $toCurrency === 'SOL' ? 4 : 2);
    }

    /**
     * Konvertuj iznos u EUR
     */
    public function convertToEur(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'EUR') {
            return $amount;
        }

        $rates = $this->getRates();
        $rate = $rates[$fromCurrency] ?? 1;

        return round($amount / $rate, 2);
    }

    /**
     * Formatiraj iznos sa simbolom valute
     */
    public function format(float $amount, string $currency): string
    {
        $symbol = self::CURRENCIES[$currency]['symbol'] ?? $currency;
        $decimals = $currency === 'SOL' ? 4 : 2;
        
        return $symbol . number_format($amount, $decimals);
    }

    /**
     * Dobij listu valuta
     */
    public function getCurrencies(): array
    {
        return self::CURRENCIES;
    }
}
