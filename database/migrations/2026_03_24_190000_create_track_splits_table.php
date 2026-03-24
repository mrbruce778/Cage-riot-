<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('track_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->foreign('track_id')
                ->references('id')
                ->on('tracks')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(['track_id', 'user_id']);

            $table->index('track_id');
            $table->index('user_id');
        });

        DB::statement("
            ALTER TABLE track_splits
            ADD CONSTRAINT track_splits_percentage_check
            CHECK (percentage >= 0 AND percentage <= 100)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('track_splits');
    }
};