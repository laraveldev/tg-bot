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
        Schema::create('lunch_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // users_management jadvalidagi ID
            $table->unsignedBigInteger('lunch_schedule_id');
            $table->datetime('scheduled_start_time'); // Rejalashtirilgan boshlanish vaqti
            $table->datetime('scheduled_end_time'); // Rejalashtirilgan tugash vaqti
            $table->datetime('actual_start_time')->nullable(); // Haqiqiy boshlanish vaqti
            $table->datetime('actual_end_time')->nullable(); // Haqiqiy tugash vaqti
            $table->enum('status', ['scheduled', 'reminded', 'started', 'completed', 'missed'])->default('scheduled');
            $table->text('notes')->nullable(); // Qo'shimcha izohlar
            $table->boolean('reminder_sent')->default(false); // 5 daqiqa oldin eslatma yuborilganmi
            $table->boolean('supervisor_notified')->default(false); // Supervisor xabardor qilinganmi
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users_management')->onDelete('cascade');
            $table->foreign('lunch_schedule_id')->references('id')->on('lunch_schedules')->onDelete('cascade');
            $table->index(['status', 'scheduled_start_time']);
            $table->index(['user_id', 'status']);
            $table->index('scheduled_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lunch_breaks');
    }
};
