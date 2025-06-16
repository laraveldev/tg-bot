<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DefStudio\Telegraph\Models\TelegraphBot;
use App\Models\UserManagement;

class SendRegistrationInvite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-registration-invite {--group-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send registration invite to group members';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $groupId = $this->option('group-id') ?: config('telegraph.chat_id') ?: env('TELEGRAPH_CHAT_ID');
        
        if (!$groupId) {
            $this->error('Group chat ID not found!');
            return 1;
        }
        
        $bot = TelegraphBot::first();
        if (!$bot) {
            $this->error('No bot found!');
            return 1;
        }
        
        // Get current statistics
        $totalUsers = UserManagement::count();
        $operators = UserManagement::operators()->count();
        $supervisors = UserManagement::supervisors()->count();
        
        // Prepare registration message
        $message = "📢 DIQQAT: Tushlik tizimidan foydalanish uchun ro'yxatdan o'ting!\n\n";
        $message .= "🤖 Bot orqali tushlik jadvalini boshqarish va navbatni kuzatish imkoniyati mavjud.\n\n";
        $message .= "✅ Ro'yxatdan o'tish uchun:\n";
        $message .= "1️⃣ Botga shaxsiy xabar yuboring: @" . ($bot->name ?? 'elnurbekbot') . "\n";
        $message .= "2️⃣ /register_me buyrug'ini yuboring\n";
        $message .= "3️⃣ /start bosib telefon raqamingizni kiriting\n\n";
        
        $message .= "📊 Hozirgi holat:\n";
        $message .= "👥 Ro'yxatdan o'tganlar: {$totalUsers} kishi\n";
        $message .= "👨‍💼 Supervisors: {$supervisors}\n";
        $message .= "👨‍💻 Operators: {$operators}\n\n";
        
        $message .= "⏰ Tushlik vaqti: 13:00 - 14:00\n";
        $message .= "🍽️ Har guruhda 4 kishi tushlikka chiqadi\n\n";
        
        $message .= "❓ Savollar uchun: @elnurbek\n";
        $message .= "🚀 Bot: Laravel + Telegraph";
        
        try {
            // Send message to group using direct API call
            $url = "https://api.telegram.org/bot{$bot->token}/sendMessage";
            
            $data = [
                'chat_id' => $groupId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                
                if ($result && $result['ok']) {
                    $this->info('✅ Registration invite sent to group successfully!');
                    $this->info("📊 Current stats: {$totalUsers} total, {$supervisors} supervisors, {$operators} operators");
                    return 0;
                } else {
                    $this->error('❌ Telegram API error: ' . ($result['description'] ?? 'Unknown error'));
                    return 1;
                }
            } else {
                $this->error('❌ HTTP error: ' . $httpCode);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send message: ' . $e->getMessage());
            return 1;
        }
    }
}

