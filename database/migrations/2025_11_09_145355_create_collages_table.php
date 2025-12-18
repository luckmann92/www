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
        Schema::create('collages', function (Blueprint $table) {
             $table->increments('id');
            $table->string('title');
            $table->text('prompt');
            $table->string('preview_path');
            $table->boolean('is_active')->default(true);
            $table->integer('price')->default(250);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collages');
    }
};
