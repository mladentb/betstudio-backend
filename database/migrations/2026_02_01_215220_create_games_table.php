<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->onDelete('cascade');
            $table->string('api_game_id')->unique(); // ID iz API-ja
            $table->string('home_team');
            $table->string('away_team');
            $table->dateTime('game_datetime');
            $table->string('venue')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, live, finished, cancelled
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->json('live_data')->nullable(); // Za live rezultate
            $table->boolean('is_available_for_sale')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};