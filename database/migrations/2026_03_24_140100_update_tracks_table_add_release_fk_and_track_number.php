<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->integer('track_number')->nullable()->after('release_id');

            $table->foreign('release_id')
                ->references('id')
                ->on('releases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropForeign(['release_id']);
            $table->dropColumn('track_number');
        });
    }
};