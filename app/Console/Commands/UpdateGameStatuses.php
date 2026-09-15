<?php

namespace App\Console\Commands;

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateGameStatuses extends Command
{
    protected $signature = 'games:update-statuses';
    protected $description = 'Update game statuses based on scheduled time (live when started, finished after 2 hours)';

    public function handle()
    {
        $now = Carbon::now();
        
        // 1. Find scheduled games that should be LIVE (started but less than 2 hours ago)
        $gamesToStart = Game::where('status', 'scheduled')
            ->where('game_datetime', '<=', $now)
            ->where('game_datetime', '>', $now->copy()->subHours(2))
            ->get();

        $startedCount = 0;
        foreach ($gamesToStart as $game) {
            $game->update(['status' => 'live']);
            $startedCount++;
            
            Log::info("Game started (now LIVE)", [
                'game_id' => $game->id,
                'home' => $game->home_team,
                'away' => $game->away_team,
                'scheduled' => $game->game_datetime,
            ]);
        }

        // 2. Find LIVE games that should be FINISHED (started more than 2 hours ago)
        $gamesToFinish = Game::where('status', 'live')
            ->where('game_datetime', '<=', $now->copy()->subHours(2))
            ->get();

        $finishedCount = 0;
        foreach ($gamesToFinish as $game) {
            $game->update(['status' => 'finished']);
            $finishedCount++;
            
            Log::info("Game finished", [
                'game_id' => $game->id,
                'home' => $game->home_team,
                'away' => $game->away_team,
                'scheduled' => $game->game_datetime,
            ]);
        }

        // 3. Also check for any scheduled games older than 2 hours that were missed
        // (in case the scheduler wasn't running)
        $missedGames = Game::where('status', 'scheduled')
            ->where('game_datetime', '<=', $now->copy()->subHours(2))
            ->get();

        $missedCount = 0;
        foreach ($missedGames as $game) {
            $game->update(['status' => 'finished']);
            $missedCount++;
            
            Log::info("Missed game marked as finished", [
                'game_id' => $game->id,
                'home' => $game->home_team,
                'away' => $game->away_team,
                'scheduled' => $game->game_datetime,
            ]);
        }

        $this->info("Game statuses updated:");
        $this->info("  - Started (now live): {$startedCount}");
        $this->info("  - Finished: {$finishedCount}");
        $this->info("  - Missed (marked finished): {$missedCount}");

        return Command::SUCCESS;
    }
}
