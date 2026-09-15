<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\League;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SyncGamesCommand extends Command
{
    protected $signature = 'games:sync {--league= : Specific league ID to sync} {--all : Sync all leagues}';
    protected $description = 'Sync games from external APIs (DScore, Arkus)';

    public function handle()
    {
        $this->info('🔄 Starting games sync...');
        $startTime = now();

        if ($this->option('league')) {
            $league = League::find($this->option('league'));
            if (!$league) {
                $this->error('League not found!');
                return 1;
            }
            $this->syncLeague($league);
        } else {
            $leagues = League::where('is_active', true)->get();
            $this->info("Found {$leagues->count()} active leagues");
            
            foreach ($leagues as $league) {
                $this->syncLeague($league);
            }
        }

        $duration = now()->diffInSeconds($startTime);
        $this->newLine();
        $this->info("✅ Sync completed in {$duration} seconds");
        
        return 0;
    }

    private function syncLeague(League $league)
    {
        $this->info("  → Syncing: {$league->name}");

        try {
            $count = match($league->api_source) {
                'dscore' => $this->syncDScoreGames($league),
                'arkus' => $this->syncArkusGames($league),
                default => 0
            };
            
            $this->info("    ✓ {$count} games synced");
        } catch (\Exception $e) {
            $this->error("    ✗ Error: {$e->getMessage()}");
        }
    }

    private function syncDScoreGames(League $league): int
    {
        // Use league-specific token if available, otherwise fallback to default
        $token = $league->api_token ?: config('services.dscore.token');
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->withoutVerifying()->timeout(30)->get($league->api_endpoint);

        if (!$response->successful()) {
            throw new \Exception("API returned status: {$response->status()}");
        }

        $games = $response->json('contests', []);
        $count = 0;
        $now = Carbon::now();

        foreach ($games as $gameData) {
            $gameDateTime = Carbon::parse($gameData['time']);
            
            $status = $this->determineStatus($gameDateTime, $gameData['status'] ?? null, $now);

            $homeScore = null;
            $awayScore = null;
            
            if (isset($gameData['score'])) {
                $homeScore = ($gameData['score']['A1'] ?? 0) + ($gameData['score']['A2'] ?? 0);
                $awayScore = ($gameData['score']['B1'] ?? 0) + ($gameData['score']['B2'] ?? 0);
            }

            Game::updateOrCreate(
                ['api_game_id' => 'dscore_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['first_team']['name'] ?? 'TBD',
                    'away_team' => $gameData['second_team']['name'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['arena']['name'] ?? null,
                    'status' => $status,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'is_available_for_sale' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function syncArkusGames(League $league): int
    {
        $response = Http::withoutVerifying()->timeout(30)->get($league->api_endpoint);

        if (!$response->successful()) {
            throw new \Exception("API returned status: {$response->status()}");
        }

        $games = $response->json();
        $count = 0;
        $now = Carbon::now();

        foreach ($games as $gameData) {
            if (!isset($gameData['vreme_odigravanja'])) {
                continue;
            }

            $gameDateTime = Carbon::parse($gameData['vreme_odigravanja']);
            $isFinished = ($gameData['odigrano'] ?? 0) == 1;
            
            $status = $isFinished ? 'finished' : $this->determineStatus($gameDateTime, null, $now);

            Game::updateOrCreate(
                ['api_game_id' => 'arkus_' . $gameData['id']],
                [
                    'league_id' => $league->id,
                    'home_team' => $gameData['domacin'] ?? 'TBD',
                    'away_team' => $gameData['gost'] ?? 'TBD',
                    'game_datetime' => $gameDateTime,
                    'venue' => $gameData['mesto_odigravanja'] ?? null,
                    'status' => $status,
                    'home_score' => $gameData['ekipa1_rez_kraj'] ?? null,
                    'away_score' => $gameData['ekipa2_rez_kraj'] ?? null,
                    'is_available_for_sale' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function determineStatus(Carbon $gameDateTime, ?string $apiStatus, Carbon $now): string
    {
        if ($apiStatus === 'live' || $apiStatus === 'in_progress') {
            return 'live';
        }
        
        if ($apiStatus === 'finished' || $apiStatus === 'completed') {
            return 'finished';
        }

        // Auto-detect based on time
        $diffMinutes = $now->diffInMinutes($gameDateTime, false);
        
        if ($diffMinutes < -120) {
            // More than 2 hours past start time
            return 'finished';
        } elseif ($diffMinutes < 0 && $diffMinutes >= -120) {
            // Started but less than 2 hours ago - likely live
            return 'live';
        }
        
        return 'scheduled';
    }
}
