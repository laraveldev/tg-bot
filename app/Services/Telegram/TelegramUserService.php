<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\DB;
use App\Models\UserManagement;
use App\Services\Telegram\AdminService;
use Illuminate\Support\Facades\Log;

class TelegramUserService
{
    /**
     * Update user data in telegraph_chats table
     */
    public function updateUserData(int $chatId, ?array $userData = null): void
    {
        if (!$userData) {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userData = $message['from'] ?? null;
        }

        if ($userData) {
            DB::table('telegraph_chats')
                ->where('id', $chatId)
                ->update([
                    'first_name' => $userData['first_name'] ?? null,
                    'last_name' => $userData['last_name'] ?? null,
                    'username' => $userData['username'] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Get user data from telegraph_chats table
     */
    public function getUserData(int $chatId): array
    {
        $data = DB::table('telegraph_chats')
            ->where('id', $chatId)
            ->first();

        return [
            'first_name' => $data->first_name ?? null,
            'last_name' => $data->last_name ?? null,
            'username' => $data->username ?? null,
            'phone_number' => $data->phone_number ?? null,
        ];
    }

    /**
     * Update contact information
     */
    public function updateContact(int $chatId, array $contact, ?array $from = null): void
    {
        $updateData = [
            'phone_number' => $contact['phone_number'],
            'updated_at' => now(),
        ];

        // Also update other user data if available
        if ($from) {
            $updateData = array_merge($updateData, [
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'username' => $from['username'] ?? null,
            ]);
        }

        // Update telegraph_chats table
        DB::table('telegraph_chats')
            ->where('id', $chatId)
            ->update($updateData);
            
        // Also update UserManagement table if user exists
        if ($from && isset($from['id'])) {
            $user = UserManagement::where('telegram_user_id', $from['id'])->first();
            if ($user) {
                $userUpdateData = ['phone_number' => $contact['phone_number']];
                
                // Update other fields if they're empty
                if (!$user->first_name && !empty($from['first_name'])) {
                    $userUpdateData['first_name'] = $from['first_name'];
                }
                if (!$user->last_name && !empty($from['last_name'])) {
                    $userUpdateData['last_name'] = $from['last_name'];
                }
                if (!$user->username && !empty($from['username'])) {
                    $userUpdateData['username'] = $from['username'];
                }
                
                $user->update($userUpdateData);
                
                Log::info('Updated user contact and data in UserManagement', [
                    'user_id' => $from['id'],
                    'phone_number' => $contact['phone_number'],
                    'updated_fields' => array_keys($userUpdateData)
                ]);
            }
        }
    }

    /**
     * Get or create user in management system
     */
    public function getOrCreateUser(int $chatId, ?int $userId = null): UserManagement
    {
        $userData = $this->getUserData($chatId);
        $adminService = new AdminService();
        
        // Check admin status and update if needed
        $isAdmin = $adminService->checkAndStoreUserAdminStatus($chatId, $userId);
        
        // For group chats, if user is not admin but is a group member,
        // make sure they are stored as operator
        if ($chatId < 0 && !$isAdmin && $userId) {
            $message = request()->input('message') ?? request()->input('edited_message');
            $userInfo = $message['from'] ?? [];
            
            // Store as operator if not already in system
            $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
            if (!$existingUser) {
                // Make sure we have user data
                $firstName = $userInfo['first_name'] ?? null;
                $lastName = $userInfo['last_name'] ?? null;
                $username = $userInfo['username'] ?? null;
                
                // If first_name is empty, try to get it from last_name or username
                if (!$firstName) {
                    if ($lastName) {
                        $firstName = $lastName;
                        $lastName = null;
                    } elseif ($username) {
                        $firstName = $username;
                    } else {
                        $firstName = 'User ' . $userId; // Fallback
                    }
                }
                
                Log::info('Auto-registering user with data', [
                    'user_id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'original_data' => $userInfo
                ]);
                
                $newUser = UserManagement::create([
                    'telegram_chat_id' => $userId, // Use user ID for cross-chat identification
                    'telegram_user_id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'role' => UserManagement::ROLE_OPERATOR,
                    'status' => UserManagement::STATUS_ACTIVE,
                    'is_available_for_lunch' => true, // Default to available for lunch
                ]);
                
                Log::info('Auto-registered group member as operator', [
                    'user_id' => $userId,
                    'group_chat_id' => $chatId,
                    'full_name' => trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')),
                    'username' => $userInfo['username'] ?? null,
                    'role' => UserManagement::ROLE_OPERATOR
                ]);
                
                // Send welcome message to user privately if possible
                try {
                    $bot = \DefStudio\Telegraph\Models\TelegraphBot::first();
                    if ($bot) {
                        $welcomeMessage = "🎉 Salom! Siz tizimga operator sifatida ro'yxatdan o'tdingiz.\n\n";
                        $welcomeMessage .= "🍽️ Tushlik tizimidan foydalanish uchun botga shaxsan /start yuboring.";
                        
                        // Try to send private message
                        $bot->chat($userId)->message($welcomeMessage)->send();
                    }
                } catch (\Exception $e) {
                    // Ignore if we can't send private message
                    Log::info('Could not send welcome message to new operator', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                // Update existing user info if needed
                $needsUpdate = false;
                $updateData = [];
                
                // Always try to update empty fields with available data
                if (!$existingUser->first_name && !empty($userInfo['first_name'])) {
                    $updateData['first_name'] = $userInfo['first_name'];
                    $needsUpdate = true;
                }
                if (!$existingUser->last_name && !empty($userInfo['last_name'])) {
                    $updateData['last_name'] = $userInfo['last_name'];
                    $needsUpdate = true;
                }
                if (!$existingUser->username && !empty($userInfo['username'])) {
                    $updateData['username'] = $userInfo['username'];
                    $needsUpdate = true;
                }
                
                // If still no first_name, try to fix it
                if (!$existingUser->first_name && !isset($updateData['first_name'])) {
                    if (!empty($userInfo['last_name'])) {
                        $updateData['first_name'] = $userInfo['last_name'];
                        $needsUpdate = true;
                    } elseif (!empty($userInfo['username'])) {
                        $updateData['first_name'] = $userInfo['username'];
                        $needsUpdate = true;
                    } elseif (!$existingUser->first_name) {
                        $updateData['first_name'] = 'User ' . $userId;
                        $needsUpdate = true;
                    }
                }
                
                if ($needsUpdate) {
                    $existingUser->update($updateData);
                    Log::info('Updated existing user info', [
                        'user_id' => $userId,
                        'update_data' => $updateData,
                        'original_data' => $userInfo
                    ]);
                }
            }
        }
        
        Log::info('User lookup details', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'is_admin' => $isAdmin,
            'chat_type' => $chatId < 0 ? 'group' : 'private'
        ]);
        
        // Find existing user by user_id first (most reliable)
        $user = null;
        if ($userId) {
            // Always prioritize user_id lookup first
            $user = UserManagement::where('telegram_user_id', $userId)->first();
            Log::info('User lookup by user_id', [
                'user_id' => $userId,
                'found' => $user ? true : false,
                'role' => $user ? $user->role : null
            ]);
        }
        
        // If no user found by user_id, and this is a private chat, try chat_id lookup
        if (!$user && $chatId > 0) {
            $user = UserManagement::where('telegram_chat_id', $chatId)->first();
            Log::info('User lookup by chat_id (private)', [
                'chat_id' => $chatId,
                'found' => $user ? true : false,
                'role' => $user ? $user->role : null
            ]);
        }
        
        // If this is a private chat and we found a user, make sure they can access this chat
        if ($chatId > 0 && $user && $user->telegram_chat_id != $chatId) {
            // Update the chat_id for private chats to current chat
            $user->telegram_chat_id = $chatId;
            $user->save();
            
            Log::info('Updated private chat ID for user', [
                'user_id' => $userId,
                'old_chat_id' => $user->telegram_chat_id,
                'new_chat_id' => $chatId,
                'role' => $user->role
            ]);
        }
        
        // For group chats, if we found a user by user_id but they don't have group chat access,
        // we still want to use their role from private chat data
        if ($chatId < 0 && $user && $user->telegram_chat_id != $chatId) {
            // User exists but might not have group chat record
            // Use their existing role but don't update chat_id to group chat
            Log::info('Group chat: Using existing user data', [
                'user_chat_id' => $user->telegram_chat_id,
                'group_chat_id' => $chatId,
                'role' => $user->role
            ]);
            return $user;
        }
        
        if ($user) {
            return $this->updateExistingUser($user, $chatId, $userId, $isAdmin);
        }
        
        return $this->createNewUser($chatId, $userId, $userData, $isAdmin);
    }

    /**
     * Update existing user
     */
    private function updateExistingUser(UserManagement $user, int $chatId, ?int $userId = null, bool $isAdmin = false): UserManagement
    {
        $needsUpdate = false;
        
        // Update chat_id if different - but check for duplicates first
        if ($user->telegram_chat_id != $chatId) {
            // Check if another user already has this chat_id
            $existingUser = UserManagement::where('telegram_chat_id', $chatId)
                ->where('id', '!=', $user->id)
                ->first();
            
            if (!$existingUser) {
                $user->telegram_chat_id = $chatId;
                $needsUpdate = true;
            }
        }
        
        // Update user_id if not set
        if (!$user->telegram_user_id && $userId) {
            $user->telegram_user_id = $userId;
            $needsUpdate = true;
        }
        
        // Update role based on admin status
        if ($isAdmin && $user->role !== UserManagement::ROLE_SUPERVISOR) {
            $user->role = UserManagement::ROLE_SUPERVISOR;
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $user->save();
        }
        
        return $user;
    }

    /**
     * Create new user
     */
    private function createNewUser(int $chatId, ?int $userId = null, array $userData = [], bool $isAdmin = false): UserManagement
    {
        // Default role is OPERATOR for all group members who are not admin/owner
        $defaultRole = UserManagement::ROLE_OPERATOR;
        
        if ($isAdmin) {
            $defaultRole = UserManagement::ROLE_SUPERVISOR;
        }
        
        return UserManagement::create([
            'telegram_chat_id' => $chatId,
            'telegram_user_id' => $userId,
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'username' => $userData['username'],
            'phone_number' => $userData['phone_number'],
            'role' => $defaultRole,
            'status' => UserManagement::STATUS_ACTIVE,
        ]);
    }
}

