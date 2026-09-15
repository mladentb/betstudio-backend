<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use App\Models\League;
use App\Models\Game;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SyncController extends Controller
{
    private $dscoreBaseUrl = 'https://new-api.dscore.live';

    public function syncSports()
    {
        $sports = [
            ['name' => 'Rukomet', 'slug' => 'rukomet', 'api_source' => 'dscore'],
            ['name' => 'Odbojka', 'slug' => 'odbojka', 'api_source' => 'dscore'],
        ];

        foreach ($sports as $sportData) {
            Sport::updateOrCreate(
                ['slug' => $sportData['slug']],
                $sportData
            );
        }

        return response()->json(['message' => 'Sports synced successfully', 'count' => count($sports)]);
    }

    public function syncLeagues()
    {
        $rukometSport = Sport::where('slug', 'rukomet')->first();
        
        $rukometLeagues = [
            [
                'sport_id' => $rukometSport->id,
                'name' => 'MLS Liga',
                'slug' => 'mls-liga',
                'api_league_id' => '184',
                'api_endpoint' => $this->dscoreBaseUrl . '/leagues/184/public-schedule',
                'api_source' => 'dscore',
                'gender' => 'male'
            ],
            [
                'sport_id' => $rukometSport->id,
                'name' => 'Arkus Liga - Muškarci',
                'slug' => 'arkus-liga-muskarci',
                'api_league_id' => '46',
                'api_endpoint' => 'https://arkus-liga.rs/phprest/api/read_utakmice_rasp.php?id=46',
                'api_source' => 'arkus',
                'gender' => 'male'
            ],
            [
                'sport_id' => $rukometSport->id,
                'name' => 'Arkus Liga - Žene',
                'slug' => 'arkus-liga-zene',
                'api_league_id' => '50',
                'api_endpoint' => 'https://arkus-liga.rs/phprest/api/read_utakmice_rasp.php?id=50',
                'api_source' => 'arkus',
                'gender' => 'female'
            ]
        ];

        foreach ($rukometLeagues as $leagueData) {
            League::updateOrCreate(
                ['slug' => $leagueData['slug']],
                $leagueData
            );
        }

        return response()->json(['message' => 'Leagues synced successfully', 'count' => count($rukometLeagues)]);
    }

    public function syncGames(League $league)
    {
        $gamesCount = 0;

        if ($league->api_source === 'dscore') {
            $gamesCount = $this->syncDScoreGames($league);
        } elseif ($league->api_source === 'arkus') {
            $gamesCount = $this->syncArkusGames($league);
        } elseif ($league->api_source === 'custom') {
            // Custom API - try DScore format first (most common)
            $gamesCount = $this->syncCustomApiGames($league);
        }

        return response()->json([
            'message' => 'Games synced successfully',
            'league' => $league->name,
            'count' => $gamesCount
        ]);
    }

    /**
     * Sync games from custom API endpoint
     * Supports DScore-like format with Bearer token auth
     */
    private function syncCustomApiGames(League $league)
    {
        $headers = [];
        
        // Add Bearer token if available
        if ($league->api_token) {
            $headers['Authorization'] = 'Bearer ' . $league->api_token;
        }
        
        $response = Http::withHeaders($headers)
            ->withoutVerifying()  // Skip SSL verification for local/dev
            ->timeout(30)
            ->get($league->api_endpoint);

        if (!$response->successful()) {
            \Log::error('Custom API failed', [
                'league_id' => $league->id,
                'endpoint' => $league->api_endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return 0;
        }

        $data = $response->json();
        
        // Try DScore format first (contests array)
        if (isset($data['contests'])) {
            return $this->parseDScoreFormat($league, $data['contests']);
        }
        
        // Try direct array format
        if (is_array($data) && !empty($data)) {
            // Check if it looks like real Arkus format (domacin, gost, vreme_odigravanja)
            if (isset($data[0]['domacin']) || isset($data[0]['gost']) || isset($data[0]['vreme_odigravanja'])) {
                return $this->parseRealArkusFormat($league, $data);
            }
            // Check if it looks like simple Arkus format
            if (isset($data[0]['match_id']) || isset($data[0]['home']) || isset($data[0]['away'])) {
                return $this->parseArkusFormat($league, $data);
            }
            // Check if it looks like DScore format
            if (isset($data[0]['first_team']) || isset($data[0]['second_team'])) {
                return $this->parseDScoreFormat($league, $data);
            }
        }
        
        \Log::warning('Unknown API format', [
            'league_id' => $league->id,
            'sample' => array_slice($data, 0, 1)
        ]);
        
        return 0;
    }

    private function parseDScoreFormat(League $league, array $games)
    {
        $count = 0;
        
        foreach ($games as $gameData) {
            $gameDateTime = Carbon::parse($gameData['time']);
            
            $homeScore = null;
            $awayScore = null;
            
            if (isset($gameData['score'])) {
                // Sum all periods (works for volleyball, handball, basketball)
                $homeScore = 0;
                $awayScore = 0;
                foreach (['A1', 'A2', 'A3', 'A4', 'A5'] as $key) {
                    $homeScore += $gameData['score'][$key] ?? 0;
                }
                foreach (['B1', 'B2', 'B3', 'B4', 'B5'] as $key) {
                    $awayScore += $gameData['score'][$key] ?? 0;
                }
            }
            
            Game::updateOrCreate(
                ['api_game_id' => 'custom_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['first_team']['name'] ?? 'TBD',
                    'away_team' => $gameData['second_team']['name'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['arena']['name'] ?? null,
                    'status' => $gameData['status'] ?? 'scheduled',
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                ]
            );

            $this->createDefaultPrices(Game::where('api_game_id', 'custom_' . $gameData['id'])->first(), $gameDateTime);
            $count++;
        }
        
        return $count;
    }
    
    private function parseArkusFormat(League $league, array $games)
    {
        $count = 0;
        
        foreach ($games as $gameData) {
            $gameDateTime = Carbon::parse($gameData['date'] ?? $gameData['datetime']);
            
            Game::updateOrCreate(
                ['api_game_id' => 'custom_arkus_' . ($gameData['match_id'] ?? $gameData['id'])],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['home'] ?? $gameData['home_team'] ?? 'TBD',
                    'away_team' => $gameData['away'] ?? $gameData['away_team'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['venue'] ?? $gameData['hall'] ?? null,
                    'status' => $this->mapStatus($gameData['status'] ?? 'scheduled'),
                    'home_score' => $gameData['home_score'] ?? null,
                    'away_score' => $gameData['away_score'] ?? null,
                ]
            );

            $this->createDefaultPrices(
                Game::where('api_game_id', 'custom_arkus_' . ($gameData['match_id'] ?? $gameData['id']))->first(), 
                $gameDateTime
            );
            $count++;
        }
        
        return $count;
    }

    /**
     * Parse real Arkus API format with Serbian field names
     * Fields: domacin, gost, vreme_odigravanja, mesto_odigravanja, odigrano
     * Scores: ekipa1_rez_kraj, ekipa2_rez_kraj, ekipa1_rez_poluvreme, ekipa2_rez_poluvreme
     */
    private function parseRealArkusFormat(League $league, array $games)
    {
        $count = 0;
        
        foreach ($games as $gameData) {
            $gameDateTime = Carbon::parse($gameData['vreme_odigravanja']);
            
            // Determine status based on 'odigrano' field (1 = played/finished, 0 = scheduled)
            $status = 'scheduled';
            if (isset($gameData['odigrano']) && $gameData['odigrano'] == 1) {
                $status = 'finished';
            }
            
            // Get final scores (ekipa1 = home/domacin, ekipa2 = away/gost)
            $homeScore = $gameData['ekipa1_rez_kraj'] ?? null;
            $awayScore = $gameData['ekipa2_rez_kraj'] ?? null;
            
            // Store halftime scores in live_data JSON
            $liveData = null;
            if (isset($gameData['ekipa1_rez_poluvreme']) || isset($gameData['ekipa2_rez_poluvreme'])) {
                $liveData = [
                    'halftime' => [
                        'home' => $gameData['ekipa1_rez_poluvreme'] ?? null,
                        'away' => $gameData['ekipa2_rez_poluvreme'] ?? null,
                    ],
                    'final' => [
                        'home' => $homeScore,
                        'away' => $awayScore,
                    ],
                    'kolo' => $gameData['kolo'] ?? null,
                    'sezona' => $gameData['takm_sezona'] ?? null,
                ];
            }
            
            Game::updateOrCreate(
                ['api_game_id' => 'arkus_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['domacin'] ?? 'TBD',
                    'away_team' => $gameData['gost'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['mesto_odigravanja'] ?? null,
                    'status' => $status,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'live_data' => $liveData,
                ]
            );

            $this->createDefaultPrices(
                Game::where('api_game_id', 'arkus_' . $gameData['id'])->first(), 
                $gameDateTime
            );
            $count++;
        }
        
        return $count;
    }
    
    private function mapStatus($status)
    {
        $map = [
            'scheduled' => 'scheduled',
            'live' => 'live',
            'in_progress' => 'live',
            'finished' => 'finished',
            'completed' => 'finished',
            'cancelled' => 'cancelled',
            'postponed' => 'scheduled',
        ];
        
        return $map[strtolower($status)] ?? 'scheduled';
    }

    private function syncDScoreGames(League $league)
    {
        // Use league-specific token if available, otherwise fallback to default
        $token = $league->api_token ?: config('services.dscore.token');
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get($league->api_endpoint);

        if (!$response->successful()) {
            \Log::error('DScore API failed', [
                'league_id' => $league->id,
                'endpoint' => $league->api_endpoint,
                'status' => $response->status()
            ]);
            return 0;
        }

        $games = $response->json('contests', []);
        
        \Log::info('DScore games fetched', [
            'league_id' => $league->id,
            'count' => count($games)
        ]);
        
        $count = 0;

        foreach ($games as $gameData) {
            $gameDateTime = Carbon::parse($gameData['time']);
            
            $homeScore = null;
            $awayScore = null;
            
            if (isset($gameData['score'])) {
                $homeScore = ($gameData['score']['A1'] ?? 0) + ($gameData['score']['A2'] ?? 0) + 
                            ($gameData['score']['A3'] ?? 0) + ($gameData['score']['A4'] ?? 0);
                $awayScore = ($gameData['score']['B1'] ?? 0) + ($gameData['score']['B2'] ?? 0) + 
                            ($gameData['score']['B3'] ?? 0) + ($gameData['score']['B4'] ?? 0);
            }
            
            $game = Game::updateOrCreate(
                ['api_game_id' => 'dscore_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['first_team']['name'] ?? 'TBD',
                    'away_team' => $gameData['second_team']['name'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['arena']['name'] ?? null,
                    'status' => $gameData['status'] ?? 'scheduled',
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                ]
            );

            $this->createDefaultPrices($game, $gameDateTime);
            $count++;
        }

        return $count;
    }

    private function syncArkusGames(League $league)
    {
        $response = Http::get($league->api_endpoint);

        if (!$response->successful()) {
            \Log::error('Arkus API failed', [
                'league_id' => $league->id,
                'endpoint' => $league->api_endpoint,
                'status' => $response->status()
            ]);
            return 0;
        }

        $games = $response->json();
        
        \Log::info('Arkus games fetched', [
            'league_id' => $league->id,
            'count' => count($games)
        ]);
        
        $count = 0;

        foreach ($games as $gameData) {
            if (!isset($gameData['vreme_odigravanja'])) {
                continue;
            }

            $gameDateTime = Carbon::parse($gameData['vreme_odigravanja']);
            
            $game = Game::updateOrCreate(
                ['api_game_id' => 'arkus_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['domacin'] ?? 'TBD',
                    'away_team' => $gameData['gost'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['mesto_odigravanja'] ?? null,
                    'status' => $gameData['odigrano'] == 1 ? 'finished' : 'scheduled',
                    'home_score' => $gameData['ekipa1_rez_kraj'] ?? null,
                    'away_score' => $gameData['ekipa2_rez_kraj'] ?? null,
                ]
            );

            $this->createDefaultPrices($game, $gameDateTime);
            $count++;
        }

        return $count;
    }

    private function createDefaultPrices(Game $game, Carbon $gameDateTime)
    {
        $dayOfWeek = $gameDateTime->dayOfWeek;
        $dayType = ($dayOfWeek == Carbon::SATURDAY || $dayOfWeek == Carbon::SUNDAY) ? 'weekend' : 'weekday';
        $price = $dayType === 'weekend' ? 15.00 : 10.00;

        Price::updateOrCreate(
            [
                'game_id' => $game->id,
                'day_type' => $dayType
            ],
            [
                'price' => $price,
                'is_active' => true
            ]
        );
    }

    public function syncAll()
    {
        $this->syncSports();
        $this->syncLeagues();

        $leagues = League::where('is_active', true)->get();
        $totalGames = 0;

        foreach ($leagues as $league) {
            $count = 0;
            if ($league->api_source === 'dscore') {
                $count = $this->syncDScoreGames($league);
            } elseif ($league->api_source === 'arkus') {
                $count = $this->syncArkusGames($league);
            }
            $totalGames += $count;
        }

        return response()->json([
            'message' => 'Full sync completed successfully',
            'leagues_synced' => $leagues->count(),
            'games_synced' => $totalGames
        ]);
    }
}
