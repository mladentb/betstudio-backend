<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->string('api_source')->nullable()->after('api_endpoint');
        });
        
        // Ažuriraj postojeće lige
        DB::table('leagues')
            ->where('api_endpoint', 'like', '%dscore%')
            ->update(['api_source' => 'dscore']);
            
        DB::table('leagues')
            ->where('api_endpoint', 'like', '%arkus%')
            ->update(['api_source' => 'arkus']);
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('api_source');
        });
    }
};
