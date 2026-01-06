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
            $table->string('code')->nullable()->unique();
        });

        // Заполним код для существующих заказов
        \Illuminate\Support\Facades\DB::table('orders')->orderBy('id')->chunk(100, function ($orders) {
            foreach ($orders as $order) {
                if (is_null($order->code)) {
                    $code = $this->generateUniqueCode();
                    \Illuminate\Support\Facades\DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['code' => $code]);
                }
            }
        });

        // Сделаем поле NOT NULL
        Schema::table('orders', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $part1 = rand(100, 999);
            $part2 = rand(100, 999);
            $code = "{$part1}-{$part2}";
            $existing = \Illuminate\Support\Facades\DB::table('orders')->where('code', $code)->first();
        } while ($existing);

        return $code;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
