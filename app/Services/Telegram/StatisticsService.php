<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\UserManagement;
use Exception;

class StatisticsService
{
    private TelegramUserService $userService;
    private WebhookHandler $handler;
    
    public function __construct(TelegramUserService $userService, WebhookHandler $handler)
    {
        $this->userService = $userService;
        $this->handler = $handler;
    }
    
    /**
     * Handle group statistics command
     */
    public function handleGroupStatistics(): void
    {
        $message = request()->input('message') ?? request()->input('edited_message');
        $userId = $message['from']['id'] ?? null;
        $user = $this->userService->getOrCreateUser($this->handler->getChatId(), $userId);
        
        if (!$user->isSupervisor()) {
            $this->handler->sendReply("❌ Bu buyruq faqat supervisor lar uchun!");
            return;
        }
        
        // Get statistics from database
        $totalUsers = UserManagement::count();
        $supervisors = UserManagement::supervisors()->count();
        $operators = UserManagement::operators()->count();
        $activeUsers = UserManagement::active()->count();
        
        $message = "📊 Guruh statistikasi:\n\n";
        $message .= "👥 Jami foydalanuvchilar: {$totalUsers}\n";
        $message .= "👨‍💼 Supervisors: {$supervisors}\n";
        $message .= "👨‍💻 Operators: {$operators}\n";
        $message .= "✅ Faol foydalanuvchilar: {$activeUsers}\n\n";
        
        // Get group chat info
        $groupChatId = config('telegraph.chat_id') ?? env('TELEGRAPH_CHAT_ID');
        if ($groupChatId) {
            $bot = \DefStudio\Telegraph\Models\TelegraphBot::first();
            if ($bot) {
                $memberCount = $this->getGroupMemberCount((int)$groupChatId, $bot->token);
                if ($memberCount) {
                    $message .= "🏢 Telegram guruhida: {$memberCount} kishi\n";
                    $unregistered = $memberCount - $totalUsers;
                    $message .= "❓ Ro'yxatdan o'tmagan: {$unregistered} kishi";
                }
            }
        }
        
        $this->handler->sendReply($message);
    }
    
    /**
     * Get group member count
     */
    private function getGroupMemberCount(int $chatId, string $botToken): ?int
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/getChatMemberCount";
            
            $data = ['chat_id' => $chatId];
            
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
                
                if ($result && $result['ok'] && isset($result['result'])) {
                    return $result['result'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}

