<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule lunch reminders to run every minute
Schedule::command('lunch:send-reminders')
    ->everyMinute()
    ->description('Send lunch break reminders to operators');
    
// Create daily lunch schedules at 8:00 AM
Schedule::call(function () {
    $shifts = \App\Models\WorkShift::active()->get();
    $scheduleService = new \App\Services\LunchManagement\LunchScheduleService();
    
    foreach ($shifts as $shift) {
        $scheduleService->createDailySchedule($shift);
    }
})->dailyAt('08:00')
  ->description('Create daily lunch schedules for all active shifts');
