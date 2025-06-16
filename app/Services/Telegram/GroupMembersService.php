<?php

namespace App\Services\Telegram;

use App\Models\UserManagement;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Log;
use Exception;

class GroupMembersService
{
    /**
     * Sync all group members to database
     */
    public function syncGroupMembers(int $groupChatId): array
    {
        try {
            $bot = TelegraphBot::first();
            if (!$bot) {
                return ['success' => false, 'message' => 'Bot not found'];
            }

            // Get group member count first
            $memberCount = $this->getGroupMemberCount($groupChatId, $bot->token);
            if (!$memberCount) {
                return ['success' => false, 'message' => 'Could not get member count'];
            }

            Log::info('Starting group members sync', [
                'group_chat_id' => $groupChatId,
                'estimated_members' => $memberCount
            ]);

            $syncedCount = 0;
            $newMembers = 0;
            $adminCount = 0;
            
            // Try to get group administrators first
            $admins = $this->getGroupAdministrators($groupChatId, $bot->token);
            
            foreach ($admins as $admin) {
                if ($admin['user']['is_bot']) {
                    continue; // Skip bots
                }
                
                $userId = $admin['user']['id'];
                $userInfo = $admin['user'];
                
                // Check if user already exists
                $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
                
                if (!$existingUser) {
                    // Create new admin user
                    $newUser = UserManagement::create([
                        'telegram_chat_id' => $userId,
                        'telegram_user_id' => $userId,
                        'first_name' => $userInfo['first_name'] ?? null,
                        'last_name' => $userInfo['last_name'] ?? null,
                        'username' => $userInfo['username'] ?? null,
                        'role' => UserManagement::ROLE_SUPERVISOR,
                        'status' => UserManagement::STATUS_ACTIVE,
                        'is_available_for_lunch' => true,
                    ]);
                    
                    $newMembers++;
                    Log::info('Added group admin to database', [
                        'user_id' => $userId,
                        'username' => $userInfo['username'] ?? null,
                        'full_name' => trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')),
                        'role' => 'supervisor'
                    ]);
                } else {
                    // Update existing user to supervisor if they're admin
                    if ($existingUser->role !== UserManagement::ROLE_SUPERVISOR) {
                        $existingUser->role = UserManagement::ROLE_SUPERVISOR;
                        $existingUser->save();
                        Log::info('Updated user to supervisor', ['user_id' => $userId]);
                    }
                }
                
                $adminCount++;
                $syncedCount++;
            }
            
            // Since we can't get all members directly, we'll create a command
            // that users can run to add themselves as operators
            
            return [
                'success' => true,
                'message' => 'Group sync completed',
                'synced_count' => $syncedCount,
                'new_members' => $newMembers,
                'admin_count' => $adminCount,
                'total_estimated' => $memberCount
            ];
            
        } catch (Exception $e) {
            Log::error('Group members sync failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get group administrators
     */
    private function getGroupAdministrators(int $chatId, string $botToken): array
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/getChatAdministrators";
            
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
            
            return [];
        } catch (Exception $e) {
            Log::error('Failed to get group administrators', ['error' => $e->getMessage()]);
            return [];
        }
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
            Log::error('Failed to get group member count', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Register user as operator (for group members)
     */
    public function registerAsOperator(int $userId, array $userInfo): bool
    {
        try {
            $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
            
            if ($existingUser) {
                Log::info('User already registered', ['user_id' => $userId, 'role' => $existingUser->role]);
                return true;
            }
            
            UserManagement::create([
                'telegram_chat_id' => $userId,
                'telegram_user_id' => $userId,
                'first_name' => $userInfo['first_name'] ?? null,
                'last_name' => $userInfo['last_name'] ?? null,
                'username' => $userInfo['username'] ?? null,
                'role' => UserManagement::ROLE_OPERATOR,
                'status' => UserManagement::STATUS_ACTIVE,
                'is_available_for_lunch' => true,
            ]);
            
            Log::info('Registered new operator', [
                'user_id' => $userId,
                'username' => $userInfo['username'] ?? null,
                'full_name' => trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''))
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to register operator', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

