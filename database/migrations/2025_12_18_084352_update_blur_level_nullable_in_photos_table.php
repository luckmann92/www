<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Make blur_level nullable with default value of 0
            $table->integer('blur_level')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Revert to non-nullable with default value of 0
            $table->integer('blur_level')->default(0)->change();
        });
    }
};
