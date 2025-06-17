<?php

namespace App\Services\Telegram;

use App\Models\UserManagement;
use Exception;

class AdminService
{
    /**
     * Check if user is admin in any group and store their admin status
     * This method now performs real-time checks and updates
     */
    public function checkAndStoreUserAdminStatus(int $chatId, ?int $userId = null): bool
    {
        try {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userId = $userId ?? ($message['from']['id'] ?? null);
            
            if (!$userId) {
                return false;
            }
            
            // If this is a group chat, always check admin status in real-time
            if ($chatId < 0) {
                $isAdmin = $this->checkTelegramGroupAdmin($chatId, $userId);
                
                // Get existing user record
                $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
                
                \Illuminate\Support\Facades\Log::info('Real-time admin check', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'is_admin' => $isAdmin,
                    'existing_role' => $existingUser ? $existingUser->role : 'not_found',
                    'username' => $message['from']['username'] ?? 'N/A'
                ]);
                
                if ($isAdmin) {
                    // Store or update user as supervisor using their personal user ID
                    if (!$existingUser || $existingUser->role !== UserManagement::ROLE_SUPERVISOR) {
                        $this->storeUserAsSupervisor($userId, $message['from']);
                        \Illuminate\Support\Facades\Log::info('User promoted to supervisor via real-time check', [
                            'user_id' => $userId,
                            'chat_id' => $chatId,
                            'username' => $message['from']['username'] ?? 'N/A',
                            'previous_role' => $existingUser ? $existingUser->role : 'new_user'
                        ]);
                    }
                    return true;
                } else {
                    // If user exists but is no longer admin, demote to operator
                    if ($existingUser && $existingUser->role === UserManagement::ROLE_SUPERVISOR) {
                        $this->demoteToOperator($userId, $message['from']);
                        \Illuminate\Support\Facades\Log::info('User demoted from supervisor to operator via real-time check', [
                            'user_id' => $userId,
                            'chat_id' => $chatId,
                            'username' => $message['from']['username'] ?? 'N/A'
                        ]);
                    }
                    return false;
                }
            } else {
                // This is a private chat, check if user is stored as supervisor
                return $this->isStoredSupervisor($userId);
            }
            
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin status check failed', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'user_id' => $userId ?? 'N/A'
            ]);
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
     * Store user as operator (for non-admin group members)
     */
    private function storeUserAsOperator(int $userId, array $userInfo): void
    {
        UserManagement::updateOrCreate(
            ['telegram_user_id' => $userId],
            [
                'telegram_chat_id' => $userId, // Use user ID as chat ID for private chats
                'first_name' => $userInfo['first_name'] ?? null,
                'last_name' => $userInfo['last_name'] ?? null,
                'username' => $userInfo['username'] ?? null,
                'role' => UserManagement::ROLE_OPERATOR,
                'status' => UserManagement::STATUS_ACTIVE,
            ]
        );
    }
    
    /**
     * Demote user from supervisor to operator
     */
    private function demoteToOperator(int $userId, array $userInfo): void
    {
        $user = UserManagement::where('telegram_user_id', $userId)->first();
        if ($user && $user->role === UserManagement::ROLE_SUPERVISOR) {
            $user->update([
                'role' => UserManagement::ROLE_OPERATOR,
                'first_name' => $userInfo['first_name'] ?? $user->first_name,
                'last_name' => $userInfo['last_name'] ?? $user->last_name,
                'username' => $userInfo['username'] ?? $user->username,
            ]);
        }
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

