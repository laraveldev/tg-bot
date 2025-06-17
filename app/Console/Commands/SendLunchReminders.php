<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LunchBreak;
use App\Models\UserManagement;
use DefStudio\Telegraph\Models\TelegraphBot;
use Carbon\Carbon;

class SendLunchReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lunch:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send lunch break reminders to operators 5 minutes before their scheduled time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Tushlik eslatmalarini tekshirish...');
        
        // Find lunch breaks that need reminders
        $pendingReminders = LunchBreak::pendingReminders()->with('user')->get();
        
        if ($pendingReminders->isEmpty()) {
            $this->info('✅ Eslatma yuborish kerak bo\'lgan tushlik tanaffuslari yo\'q.');
            return;
        }
        
        $bot = TelegraphBot::first();
        if (!$bot) {
            $this->error('❌ Bot topilmadi!');
            return;
        }
        
        $sentCount = 0;
        
        foreach ($pendingReminders as $lunchBreak) {
            try {
                $user = $lunchBreak->user;
                $minutesLeft = Carbon::now()->diffInMinutes($lunchBreak->scheduled_start_time);
                
                $message = "⏰ Tushlik vaqti eslatmasi!\n\n";
                $message .= "🍽️ Sizning tushlik vaqtingiz {$minutesLeft} daqiqadan so'ng:\n";
                $message .= "📅 Vaqt: {$lunchBreak->scheduled_start_time->format('H:i')} - {$lunchBreak->scheduled_end_time->format('H:i')}\n";
                $message .= "⏱️ Davomiyligi: {$lunchBreak->getScheduledDurationInMinutes()} daqiqa\n\n";
                $message .= "📝 Tushlikka chiqishdan oldin /lunch_start buyrug'ini ishlating!";
                
                // Send reminder
                $chat = \DefStudio\Telegraph\Models\TelegraphChat::where('chat_id', $user->telegram_chat_id)->first();
                if (!$chat) {
                    // Create a temporary chat for this user
                    $chat = $bot->chats()->create([
                        'chat_id' => $user->telegram_chat_id,
                        'name' => $user->full_name
                    ]);
                }
                $chat->message($message)->send();
                
                // Mark reminder as sent
                $lunchBreak->markReminderSent();
                
                $this->info("✅ Eslatma yuborildi: {$user->full_name}");
                $sentCount++;
                
            } catch (\Exception $e) {
                $this->error("❌ Eslatma yuborishda xatolik ({$user->full_name}): {$e->getMessage()}");
            }
        }
        
        $this->info("🎉 Jami {$sentCount} ta eslatma yuborildi.");
        
        // Also check for overdue lunch breaks and notify supervisors
        $this->checkOverdueLunchBreaks($bot);
    }
    
    /**
     * Check for overdue lunch breaks and notify supervisors
     */
    private function checkOverdueLunchBreaks(TelegraphBot $bot): void
    {
        $overdueLunches = LunchBreak::overdue()->with('user')->get();
        
        if ($overdueLunches->isEmpty()) {
            return;
        }
        
        $supervisors = UserManagement::supervisors()->get();
        
        foreach ($overdueLunches as $lunch) {
            if (!$lunch->supervisor_notified) {
                $overdueMinutes = Carbon::now()->diffInMinutes($lunch->scheduled_end_time);
                
                $message = "⚠️ TUSHLIK VAQTI TUGAGAN!\n\n";
                $message .= "👤 Operator: {$lunch->user->full_name}\n";
                $message .= "🕐 Rejalashtirilgan tugash vaqti: {$lunch->scheduled_end_time->format('H:i')}\n";
                $message .= "⏰ Kechikish: {$overdueMinutes} daqiqa\n\n";
                $message .= "📞 Operator bilan bog'laning!";
                
                foreach ($supervisors as $supervisor) {
                    try {
                        $chat = \DefStudio\Telegraph\Models\TelegraphChat::where('chat_id', $supervisor->telegram_chat_id)->first();
                        if (!$chat) {
                            $chat = $bot->chats()->create([
                                'chat_id' => $supervisor->telegram_chat_id,
                                'name' => $supervisor->full_name
                            ]);
                        }
                        $chat->message($message)->send();
                    } catch (\Exception $e) {
                        $this->error("❌ Supervisor'ga xabar yuborishda xatolik: {$e->getMessage()}");
                    }
                }
                
                $lunch->markSupervisorNotified();
                $this->warn("⚠️ Supervisor'larga kechikish haqida xabar yuborildi: {$lunch->user->full_name}");
            }
        }
    }
}
