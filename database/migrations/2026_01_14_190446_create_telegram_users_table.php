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
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique()->comment('ID пользователя в Telegram');
            $table->string('username')->nullable()->comment('Username пользователя в Telegram');
            $table->string('first_name')->nullable()->comment('Имя пользователя');
            $table->string('last_name')->nullable()->comment('Фамилия пользователя');
            $table->string('phone')->nullable()->comment('Телефон пользователя');
            $table->string('language_code')->nullable()->comment('Код языка пользователя');
            $table->timestamp('last_interaction_at')->nullable()->comment('Время последнего взаимодействия');
            $table->timestamps();

            $table->index('telegram_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
