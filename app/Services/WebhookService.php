<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch webhook for a game event
     */
    public static function dispatch(string $event, Game $game, array $extraData = []): void
    {
        // Find all active webhooks subscribed to this event
        $webhooks = Webhook::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->orWhereJsonContains('events', '*')
            ->get();

        foreach ($webhooks as $webhook) {
            static::send($webhook, $event, $game, $extraData);
        }
    }

    /**
     * Send webhook to a specific endpoint
     */
    public static function send(Webhook $webhook, string $event, Game $game, array $extraData = []): void
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'game' => [
                    'id' => $game->id,
                    'home_team' => $game->home_team,
                    'away_team' => $game->away_team,
                    'home_score' => $game->home_score,
                    'away_score' => $game->away_score,
                    'status' => $game->status,
                    'game_datetime' => $game->game_datetime,
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
                ],
                ...$extraData,
            ],
        ];

        // Generate signature
        $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Secret' => $webhook->secret,
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Timestamp' => now()->timestamp,
                ])
                ->post($webhook->url, $payload);

            $success = $response->successful();

            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event_type' => $event,
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'success' => $success,
                'created_at' => now(),
            ]);

            if ($success) {
                $webhook->resetFailures();
                $webhook->update(['last_triggered_at' => now()]);
            } else {
                $webhook->incrementFailure();
                Log::warning("Webhook delivery failed", [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            $webhook->incrementFailure();
            
            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event_type' => $event,
                'payload' => $payload,
                'response_status' => 0,
                'response_body' => $e->getMessage(),
                'success' => false,
                'created_at' => now(),
            ]);

            Log::error("Webhook delivery error", [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch game started event
     */
    public static function gameStarted(Game $game): void
    {
        static::dispatch('game.started', $game);
    }

    /**
     * Dispatch game finished event
     */
    public static function gameFinished(Game $game): void
    {
        static::dispatch('game.finished', $game, [
            'final_score' => "{$game->home_score} - {$game->away_score}",
        ]);
    }

    /**
     * Dispatch score updated event
     */
    public static function scoreUpdated(Game $game, int $oldHomeScore, int $oldAwayScore): void
    {
        static::dispatch('game.score_updated', $game, [
            'previous_score' => "{$oldHomeScore} - {$oldAwayScore}",
            'new_score' => "{$game->home_score} - {$game->away_score}",
        ]);
    }

    /**
     * Dispatch game created event
     */
    public static function gameCreated(Game $game): void
    {
        static::dispatch('game.created', $game);
    }

    /**
     * Dispatch game updated event
     */
    public static function gameUpdated(Game $game, array $changes): void
    {
        static::dispatch('game.updated', $game, [
            'changes' => $changes,
        ]);
    }
}
