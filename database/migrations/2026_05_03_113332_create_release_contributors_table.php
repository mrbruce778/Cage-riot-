<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_contributors', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('release_id');
            $table->uuid('contributor_id');

            // 🎭 music role
            $table->string('role');

            $table->timestamps();

            // 🔗 indexes
            $table->index('release_id');
            $table->index('contributor_id');

            // ❗ prevent duplicate same role for same contributor
            $table->unique(['release_id', 'contributor_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_contributors');
    }
};
