<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'game_id', 'price'];
    public $timestamps = false;
    protected $casts = ['price' => 'decimal:2', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function game() { return $this->belongsTo(Game::class); }
}
