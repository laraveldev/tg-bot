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
        Schema::create('lunch_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // Qaysi kun uchun jadval
            $table->unsignedBigInteger('work_shift_id');
            $table->json('operator_queue'); // Operatorlar navbati [user_id1, user_id2, ...]
            $table->integer('current_position')->default(0); // Hozirgi navbat
            $table->integer('operators_per_group')->default(2); // Har guruhda nechta operator
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('work_shift_id')->references('id')->on('work_shifts')->onDelete('cascade');
            $table->unique(['date', 'work_shift_id']);
            $table->index(['date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lunch_schedules');
    }
};
