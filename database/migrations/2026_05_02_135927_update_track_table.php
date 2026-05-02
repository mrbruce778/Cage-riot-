<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {

            // 🎵 Metadata
            $table->string('primary_genre')->nullable();
            $table->string('secondary_genre')->nullable();
            $table->string('version')->nullable();
            $table->string('language', 10)->nullable();
            $table->text('lyrics')->nullable();

            // ⚠️ Explicit flag
            $table->boolean('is_explicit')->default(false);

            // ⏱ Preview time (seconds)
            $table->integer('preview_start')->default(0);

            // 🎼 Track origin
            $table->string('track_origin')->default('original');

            // 🧩 Flexible properties
            $table->jsonb('track_properties')->nullable();

            // 📄 Conditional upload
            $table->string('sample_license_file')->nullable();

            // 🧾 Copyright
            $table->integer('copyright_year')->nullable();
            $table->string('copyright_owner')->nullable();
        });

        // ✅ Add constraint (PostgreSQL)
        DB::statement("
            ALTER TABLE tracks
            ADD CONSTRAINT track_origin_check
            CHECK (track_origin IN ('original','public_domain','cover'))
        ");
    }

    public function down(): void
    {
        // 🔥 Drop constraint first
        DB::statement("ALTER TABLE tracks DROP CONSTRAINT IF EXISTS track_origin_check");

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn([
                'primary_genre',
                'secondary_genre',
                'version',
                'language',
                'lyrics',
                'is_explicit',
                'preview_start',
                'track_origin',
                'track_properties',
                'sample_license_file',
                'copyright_year',
                'copyright_owner',
            ]);
        });
    }
};