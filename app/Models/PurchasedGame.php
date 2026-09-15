<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PurchasedGame extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'game_id', 'price_paid',
        'stream_type', 'stream_url', 'stream_key', 
        'srt_url', 'srt_passphrase',
        'stats_token', 'stats_url', 'is_active',
        'nft_mint_address', 'nft_metadata_uri', 'is_nft'
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'is_active' => 'boolean',
        'is_nft' => 'boolean',
    ];

    protected $hidden = ['stream_key', 'srt_passphrase'];

    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function game() { return $this->belongsTo(Game::class); }

    public static function generateStatsToken(): string
    {
        return Str::random(64);
    }

    public function isNFTBased(): bool
    {
        return $this->is_nft && !empty($this->nft_mint_address);
    }
}
