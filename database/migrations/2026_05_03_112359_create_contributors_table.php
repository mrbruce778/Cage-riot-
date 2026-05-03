<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name'); // required

            // 🔗 optional link to system user
            $table->uuid('user_id')->nullable()->index();

            // 🏢 multi-tenant safety
            $table->uuid('organization_id')->index();

            $table->timestamps();

            // prevent duplicates inside same org
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};