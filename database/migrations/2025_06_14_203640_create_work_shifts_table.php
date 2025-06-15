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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Erta smena", "Kech smena"
            $table->time('start_time'); // Boshlanish vaqti
            $table->time('end_time'); // Tugash vaqti
            $table->integer('lunch_duration')->default(30); // Tushlik davomiyligi (daqiqa)
            $table->integer('max_lunch_operators')->default(2); // Bir vaqtda tushlikka chiquvchilar soni
            $table->time('lunch_start_time')->nullable(); // Tushlik boshlanish vaqti
            $table->time('lunch_end_time')->nullable(); // Tushlik tugash vaqti
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_active', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
