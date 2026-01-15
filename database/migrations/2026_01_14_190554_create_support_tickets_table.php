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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade')->comment('ID пользователя Telegram');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->comment('ID связанного заказа');
            $table->text('description')->comment('Описание обращения');
            $table->enum('status', ['new', 'in_progress', 'closed'])->default('new')->comment('Статус обращения');
            $table->text('admin_response')->nullable()->comment('Ответ администратора');
            $table->timestamp('responded_at')->nullable()->comment('Время ответа администратора');
            $table->timestamps();

            $table->index(['telegram_user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
