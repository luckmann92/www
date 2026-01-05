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
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
        });

        // Заполним UUID для существующих заказов
        \Illuminate\Support\Facades\DB::table('orders')
            ->orderBy('id')  // Добавляем orderBy для корректной работы chunk
            ->chunk(100, function ($orders) {
                foreach ($orders as $order) {
                    if (is_null($order->uuid)) {
                        \Illuminate\Support\Facades\DB::table('orders')
                            ->where('id', $order->id)
                            ->update(['uuid' => \Illuminate\Support\Str::uuid()]);
                    }
                }
            });

        // Теперь можно сделать поле NOT NULL
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
