<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('organization_id');
            $table->uuid('release_id')->nullable();
            $table->uuid('track_id')->nullable();

            $table->string('asset_type', 50);
            $table->string('file_name');
            $table->text('file_path');
            $table->string('mime_type', 150)->nullable();
            $table->bigInteger('file_size')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('release_id')
                ->references('id')
                ->on('releases')
                ->onDelete('cascade');

            $table->foreign('track_id')
                ->references('id')
                ->on('tracks')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('organization_id');
            $table->index('release_id');
            $table->index('track_id');
            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};