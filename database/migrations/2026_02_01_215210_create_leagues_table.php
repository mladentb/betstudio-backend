<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->string('name'); // MLS Liga, Arkus Liga Muški, itd.
            $table->string('slug')->unique();
            $table->string('api_league_id')->nullable(); // ID iz API-ja
            $table->string('api_endpoint')->nullable(); // URL za raspored
            $table->string('gender')->nullable(); // 'male', 'female', null
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};