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
        // Удаляем старый столбец
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });

        // Создаем новый столбец с правильным типом
        Schema::table('photos', function (Blueprint $table) {
            $table->string('session_id')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем новый столбец
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });

        // Восстанавливаем старый столбец
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->after('id');
        });
    }
};
