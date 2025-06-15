<?php

namespace App\Services\Telegram;

use App\Models\UserManagement;
use Exception;

class AdminService
{
    /**
     * Check if user is admin in any group and store their admin status
     */
    public function checkAndStoreUserAdminStatus(int $chatId, ?int $userId = null): bool
    {
        try {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $userId ?? ($message['from']['id'] ?? null);
            
            if (!$userId) {
                return false;
            }
            
            // If this is a group chat, check admin status
            if ($chatId < 0) {
                $isAdmin = $this->checkTelegramGroupAdmin($chatId, $userId);
                
                if ($isAdmin) {
                    // Store or update user as supervisor using their personal user ID
                    $this->storeUserAsSupervisor($userId, $message['from']);
                    return true;
                }
            } else {
                // This is a private chat, check if user is stored as supervisor
                return $this->isStoredSupervisor($userId);
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if user is admin in Telegram group using direct API call
     */
    private function checkTelegramGroupAdmin(int $chatId, int $userId): bool
    {
        try {
            // Get the bot token from the first available bot
            $bot = \DefStudio\Telegraph\Models\TelegraphBot::first();
            if (!$bot) {
                return false;
            }
            $botToken = $bot->token;
            $url = "https://api.telegram.org/bot{$botToken}/getChatMember";
            
            $data = [
                'chat_id' => $chatId,
                'user_id' => $userId
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
                
                if ($result && $result['ok'] && isset($result['result']['status'])) {
                    $status = $result['result']['status'];
                    return in_array($status, ['creator', 'administrator']);
                }
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Store user as supervisor using their personal user ID
     */
    private function storeUserAsSupervisor(int $userId, array $userInfo): void
    {
        UserManagement::updateOrCreate(
            ['telegram_user_id' => $userId],
            [
                'telegram_chat_id' => $userId, // Use user ID as chat ID for private chats
                'first_name' => $userInfo['first_name'] ?? null,
                'last_name' => $userInfo['last_name'] ?? null,
                'username' => $userInfo['username'] ?? null,
                'role' => UserManagement::ROLE_SUPERVISOR,
                'status' => UserManagement::STATUS_ACTIVE,
            ]
        );
    }
    
    /**
     * Check if user is stored as supervisor
     */
    private function isStoredSupervisor(int $userId): bool
    {
        return UserManagement::where('telegram_user_id', $userId)
            ->where('role', UserManagement::ROLE_SUPERVISOR)
            ->exists();
    }
}

