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
        Schema::create('users_management', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->unique(); // Telegram chat ID
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('phone_number')->nullable();
            $table->enum('role', ['supervisor', 'operator'])->default('operator');
            $table->enum('status', ['active', 'inactive', 'lunch_break'])->default('active');
            $table->boolean('is_available_for_lunch')->default(true);
            $table->integer('lunch_order')->nullable(); // Tushlik navbati
            $table->unsignedBigInteger('work_shift_id')->nullable();
            $table->timestamps();
            
            $table->foreign('work_shift_id')->references('id')->on('work_shifts')->onDelete('set null');
            $table->index(['role', 'status']);
            $table->index('lunch_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_management');
    }
};
