<?php

namespace App\Services\Telegram;

use App\Models\UserManagement;
use App\Models\WorkShift;
use App\Models\LunchBreak;
use App\Services\LunchManagement\LunchScheduleService;
use App\Services\Telegram\MessageService;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class LunchCommandHandler
{
    private LunchScheduleService $scheduleService;
    private MessageService $messageService;

    public function __construct(LunchScheduleService $scheduleService, MessageService $messageService)
    {
        $this->scheduleService = $scheduleService;
        $this->messageService = $messageService;
    }

    // ============= SUPERVISOR COMMANDS =============
    
    /**
     * Show lunch status
     */
    public function handleLunchStatus(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $schedules = $this->scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            return "📊 Bugun uchun tushlik jadvali mavjud emas.\n\n"
                . "Jadval yaratish uchun /lunch_schedule buyrug'ini ishlating.";
        }
        
        $message = "📊 Bugungi tushlik holati:\n\n";
        
        foreach ($schedules as $schedule) {
            $stats = $this->scheduleService->getScheduleStats($schedule);
            
            $currentOperators = $this->scheduleService->getCurrentGroupOperators($schedule);
            $onLunchBreak = LunchBreak::today()->active()->count();
            
            $message .= "🏢 {$stats['work_shift']}:\n";
            $message .= "👥 Jami operatorlar: {$stats['total_operators']}\n";
            $message .= "📍 Hozirgi guruh: {$stats['current_group_number']}/{$stats['total_groups']}\n";
            $message .= "🍽️ Tushlikda: {$onLunchBreak} nafar\n";
            
            if ($currentOperators->isNotEmpty()) {
                $message .= "\n👤 Hozirgi guruh:\n";
                foreach ($currentOperators as $operator) {
                    $status = $operator->isOnLunchBreak() ? "🍽️" : "💻";
                    $message .= "  {$status} {$operator->full_name}\n";
                }
            }
            
            $message .= "\n";
        }
        
        return $message;
    }
    
    /**
     * Show today's lunch schedule
     */
    public function handleLunchSchedule(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $shifts = WorkShift::active()->get();
        
        if ($shifts->isEmpty()) {
            return "❌ Faol ish smenalari mavjud emas.";
        }
        
        $message = "📋 Bugungi tushlik jadvali:\n\n";
        
        foreach ($shifts as $shift) {
            $schedule = $this->scheduleService->createDailySchedule($shift);
            $operators = $this->scheduleService->getCurrentGroupOperators($schedule);
            
            $message .= "🏢 {$shift->name} ({$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')}):\n";
            $message .= "🍽️ Tushlik vaqti: {$shift->lunch_start_time->format('H:i')} - {$shift->lunch_end_time->format('H:i')}\n";
            $message .= "👥 Har guruhda: {$shift->max_lunch_operators} nafar\n\n";
            
            if ($operators->isNotEmpty()) {
                $message .= "Hozirgi guruh:\n";
                foreach ($operators as $operator) {
                    $message .= "👤 {$operator->full_name}\n";
                }
            } else {
                $message .= "❌ Hozirgi guruhda operatorlar yo'q\n";
            }
            
            $message .= "\n";
        }
        
        return $message;
    }
    
    /**
     * Move to next group
     */
    public function handleNextGroup(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $schedules = $this->scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            return "❌ Bugun uchun jadval mavjud emas.";
        }
        
        $moved = false;
        $message = "";
        
        foreach ($schedules as $schedule) {
            if ($this->scheduleService->moveToNextGroup($schedule)) {
                $moved = true;
                $nextOperators = $this->scheduleService->getCurrentGroupOperators($schedule);
                
                $message .= "✅ {$schedule->workShift->name} uchun keyingi guruhga o'tildi!\n\n";
                
                if ($nextOperators->isNotEmpty()) {
                    $message .= "👥 Keyingi guruh:\n";
                    foreach ($nextOperators as $operator) {
                        $message .= "👤 {$operator->full_name}\n";
                    }
                } else {
                    $message .= "❌ Keyingi guruhda operatorlar yo'q";
                }
                
                $message .= "\n";
            }
        }
        
        if (!$moved) {
            return "ℹ️ Barcha guruhlar tugagan yoki keyingi guruh mavjud emas.";
        }
        
        return $message;
    }
    
    /**
     * Show lunch settings
     */
    public function handleLunchSettings(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $shifts = WorkShift::active()->get();
        
        if ($shifts->isEmpty()) {
            return "❌ Faol ish smenalari mavjud emas.";
        }
        
        $message = "⚙️ Tushlik sozlamalari:\n\n";
        
        foreach ($shifts as $shift) {
            $message .= "🏢 {$shift->name}:\n";
            $message .= "📅 Ish vaqti: {$shift->start_time->format('H:i')} - {$shift->end_time->format('H:i')}\n";
            $message .= "🍽️ Tushlik vaqti: {$shift->lunch_start_time->format('H:i')} - {$shift->lunch_end_time->format('H:i')}\n";
            $message .= "👥 Maksimal operatorlar: {$shift->max_lunch_operators}\n";
            $message .= "⏱️ Tushlik davomiyligi: {$shift->lunch_duration_minutes} daqiqa\n";
            $message .= "📊 Status: " . ($shift->is_active ? '✅ Faol' : '❌ Nofaol') . "\n\n";
        }
        
        $message .= "ℹ️ Sozlamalarni o'zgartirish uchun admin panel dan foydalaning.";
        
        return $message;
    }
    
    /**
     * Show operators list
     */
    public function handleOperators(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $operators = UserManagement::operators()->get();
        $supervisors = UserManagement::supervisors()->get();
        
        $message = "👥 Foydalanuvchilar ro'yxati:\n\n";
        
        if ($supervisors->isNotEmpty()) {
            $message .= "👨‍💼 Supervisorlar:\n";
            foreach ($supervisors as $supervisor) {
                $status = $supervisor->status === 'active' ? '✅' : '❌';
                $message .= "  {$status} {$supervisor->full_name}";
                if ($supervisor->username) {
                    $message .= " (@{$supervisor->username})";
                }
                $message .= "\n";
            }
            $message .= "\n";
        }
        
        if ($operators->isNotEmpty()) {
            $message .= "👨‍💻 Operatorlar ({$operators->count()} nafar):\n";
            foreach ($operators as $operator) {
                $status = $operator->status === 'active' ? '✅' : '❌';
                $lunchStatus = $operator->isOnLunchBreak() ? '🍽️' : '💻';
                $message .= "  {$status}{$lunchStatus} {$operator->full_name}";
                if ($operator->username) {
                    $message .= " (@{$operator->username})";
                }
                if ($operator->work_shift_id) {
                    $shift = WorkShift::find($operator->work_shift_id);
                    if ($shift) {
                        $message .= " - {$shift->name}";
                    }
                }
                $message .= "\n";
            }
        } else {
            $message .= "❌ Hozircha operatorlar ro'yxatga olinmagan.\n";
        }
        
        $message .= "\n📝 Yangi operator qo'shish uchun ularni botga /start yuborishlarini so'rang.";
        
        return $message;
    }
    
    /**
     * Reorder lunch queue
     */
    public function handleReorderQueue(UserManagement $user): string
    {
        if (!$user->isSupervisor()) {
            return "❌ Bu buyruq faqat supervisor uchun!";
        }
        
        $schedules = $this->scheduleService->getTodaySchedules();
        
        if ($schedules->isEmpty()) {
            return "❌ Bugun uchun jadval mavjud emas.\n\n"
                . "Jadval yaratish uchun /lunch_schedule buyrug'ini ishlating.";
        }
        
        $reordered = false;
        $message = "🔄 Navbat qayta tartibga solinmoqda...\n\n";
        
        foreach ($schedules as $schedule) {
            try {
                // Reset queue to beginning
                $schedule->current_group_position = 1;
                $schedule->save();
                
                // Get operators for this schedule
                $operators = $this->scheduleService->getCurrentGroupOperators($schedule);
                
                $message .= "✅ {$schedule->workShift->name} navbati qayta boshlandi\n";
                $message .= "📍 Hozirgi pozitsiya: {$schedule->current_group_position}\n";
                
                if ($operators->isNotEmpty()) {
                    $message .= "👥 Hozirgi guruh:\n";
                    foreach ($operators as $operator) {
                        $message .= "  👤 {$operator->full_name}\n";
                    }
                } else {
                    $message .= "❌ Hozirgi guruhda operatorlar yo'q\n";
                }
                
                $message .= "\n";
                $reordered = true;
                
            } catch (Exception $e) {
                Log::error('Error reordering queue', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage()
                ]);
                $message .= "❌ {$schedule->workShift->name} navbatini qayta tartibga solishda xatolik\n\n";
            }
        }
        
        if (!$reordered) {
            return "❌ Navbatni qayta tartibga solishda xatolik yuz berdi.";
        }
        
        $message .= "ℹ️ Barcha navbatlar 1-pozitsiyaga qaytarildi.";
        
        return $message;
    }
    
    // ============= OPERATOR COMMANDS =============
    
    /**
     * Show operator's lunch time
     */
    public function handleMyLunch(UserManagement $user): array
    {
        if (!$user->isOperator()) {
            return [
                'message' => "❌ Bu buyruq faqat operatorlar uchun!",
                'keyboard' => null
            ];
        }
        
        $todayBreak = $user->lunchBreaks()->today()->first();
        
        if (!$todayBreak) {
            return [
                'message' => "📅 Bugun sizga tushlik vaqti belgilanmagan.\n\n" . "Supervisor bilan bog'laning.",
                'keyboard' => null
            ];
        }
        
        $message = "🍽️ Sizning tushlik vaqtingiz:\n\n";
        $message .= "📅 Sana: " . $todayBreak->scheduled_start_time->format('d.m.Y') . "\n";
        $message .= "🕐 Vaqt: " . $todayBreak->scheduled_start_time->format('H:i') . " - " . $todayBreak->scheduled_end_time->format('H:i') . "\n";
        $message .= "⏱️ Davomiyligi: " . $todayBreak->getScheduledDurationInMinutes() . " daqiqa\n";
        $message .= "📊 Holati: " . $this->getStatusEmoji($todayBreak->status) . " " . $this->getStatusText($todayBreak->status) . "\n";
        
        $keyboard = null;
        
        if ($todayBreak->status === LunchBreak::STATUS_SCHEDULED && $todayBreak->scheduled_start_time->diffInMinutes(Carbon::now()) <= 5) {
            $message .= "\n⚠️ Tushlik vaqtingiz yaqinlashdi!";
            
            $keyboard = Keyboard::make()
                ->button('✅ Tushlikka chiqdim')->action('lunch_start')
                ->button('❌ Bekor qilish')->action('cancel_lunch');
        }
        
        if ($todayBreak->status === LunchBreak::STATUS_STARTED) {
            $keyboard = Keyboard::make()
                ->button('🔙 Tushlikdan qaytdim')->action('lunch_end');
        }
        
        return [
            'message' => $message,
            'keyboard' => $keyboard
        ];
    }
    
    /**
     * Start lunch break
     */
    public function handleLunchStart(UserManagement $user): string
    {
        if (!$user->isOperator()) {
            return "❌ Bu buyruq faqat operatorlar uchun!";
        }
        
        $activeLunch = $user->getCurrentLunchBreak();
        if ($activeLunch) {
            return "⚠️ Siz allaqachon tushlik tanaffusida ekansiz!";
        }
        
        $todayBreak = $user->lunchBreaks()->today()
            ->where('status', LunchBreak::STATUS_SCHEDULED)
            ->orWhere('status', LunchBreak::STATUS_REMINDED)
            ->first();
            
        if (!$todayBreak) {
            return "❌ Bugun sizga tushlik vaqti belgilanmagan.";
        }
        
        $todayBreak->startBreak();
        
        $message = "✅ Tushlik tanaffusi boshlandi!\n\n";
        $message .= "🕐 Boshlanish vaqti: " . Carbon::now()->format('H:i') . "\n";
        $message .= "⏰ Qaytish vaqti: " . $todayBreak->scheduled_end_time->format('H:i') . "\n";
        $message .= "\n🍽️ Yaxshi ishtaha!";
        
        // Notify supervisors
        $this->messageService->notifySupervisors("🍽️ {$user->full_name} tushlik tanaffusiga chiqdi.");
        
        return $message;
    }
    
    /**
     * End lunch break
     */
    public function handleLunchEnd(UserManagement $user): string
    {
        if (!$user->isOperator()) {
            return "❌ Bu buyruq faqat operatorlar uchun!";
        }
        
        $activeLunch = $user->getCurrentLunchBreak();
        if (!$activeLunch) {
            return "❌ Siz hozir tushlik tanaffusida emassiz!";
        }
        
        $activeLunch->endBreak();
        
        $duration = $activeLunch->getDurationInMinutes();
        $message = "✅ Tushlik tanaffusi yakunlandi!\n\n";
        $message .= "🕐 Davomiyligi: {$duration} daqiqa\n";
        $message .= "💻 Ish jarayonini davom ettiring!";
        
        // Notify supervisors
        $this->messageService->notifySupervisors("💻 {$user->full_name} tushlik tanaffusidan qaytdi. ({$duration} daqiqa)");
        
        return $message;
    }
    
    // ============= HELPER METHODS =============
    
    private function getStatusEmoji(string $status): string
    {
        return match($status) {
            LunchBreak::STATUS_SCHEDULED => '📅',
            LunchBreak::STATUS_REMINDED => '⏰',
            LunchBreak::STATUS_STARTED => '🍽️',
            LunchBreak::STATUS_COMPLETED => '✅',
            LunchBreak::STATUS_MISSED => '❌',
            default => '❓'
        };
    }
    
    private function getStatusText(string $status): string
    {
        return match($status) {
            LunchBreak::STATUS_SCHEDULED => 'Rejalashtirilgan',
            LunchBreak::STATUS_REMINDED => 'Eslatma yuborilgan',
            LunchBreak::STATUS_STARTED => 'Tushlikda',
            LunchBreak::STATUS_COMPLETED => 'Yakunlangan',
            LunchBreak::STATUS_MISSED => 'O\'tkazib yuborilgan',
            default => 'Noma\'lum'
        };
    }
}

