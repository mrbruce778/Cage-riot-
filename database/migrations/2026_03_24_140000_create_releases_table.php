<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->text('title');
            $table->text('version_title')->nullable();
            $table->text('primary_artist_name')->nullable();

            $table->string('release_type')->default('single');
            $table->string('upc')->nullable();
            $table->text('label_name')->nullable();
            $table->uuid('artwork_asset_id')->nullable();

            $table->string('status')->default('draft');

            $table->uuid('organization_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->date('release_date')->nullable();
            $table->date('original_release_date')->nullable();

            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->check("release_type in ('single', 'ep', 'album')");
            $table->check("status in ('draft', 'qc', 'legal', 'approved', 'queued', 'sent', 'ingested', 'live', 'rejected')");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};