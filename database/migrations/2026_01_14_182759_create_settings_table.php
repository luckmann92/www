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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Уникальный ключ настройки');
            $table->text('value')->nullable()->comment('Значение настройки');
            $table->string('group')->default('general')->comment('Группа настроек (image_generation, payment, telegram, general)');
            $table->string('description')->nullable()->comment('Описание настройки');
            $table->timestamps();

            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
