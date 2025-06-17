<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserManagement;
use App\Services\Telegram\AdminService;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Log;

class SyncGroupMembers extends Command
{
    protected $signature = 'users:sync-group-members {--group-id=} {--limit=100}';
    protected $description = 'Fetch and sync group members from Telegram API';

    public function handle()
    {
        $groupId = $this->option('group-id') ?: config('telegraph.chat_id', '-1002768510963');
        $limit = $this->option('limit');
        
        $this->info("🔄 Fetching group members from group: {$groupId}");
        
        $bot = TelegraphBot::first();
        if (!$bot) {
            $this->error('❌ No bot found!');
            return 1;
        }
        
        $adminService = new AdminService();
        $botToken = $bot->token;
        
        try {
            // Get group administrators first
            $adminsUrl = "https://api.telegram.org/bot{$botToken}/getChatAdministrators";
            $adminsData = ['chat_id' => $groupId];
            
            $adminResponse = $this->makeApiCall($adminsUrl, $adminsData);
            
            if (!$adminResponse || !$adminResponse['ok']) {
                $this->error('❌ Failed to get group administrators');
                return 1;
            }
            
            $administrators = $adminResponse['result'];
            $this->info("Found {" . count($administrators) . "} administrators");
            
            $synced = 0;
            $updated = 0;
            
            // Process administrators
            foreach ($administrators as $admin) {
                $user = $admin['user'];
                
                // Skip bots
                if ($user['is_bot'] ?? false) {
                    continue;
                }
                
                $userId = $user['id'];
                $firstName = $user['first_name'] ?? null;
                $lastName = $user['last_name'] ?? null;
                $username = $user['username'] ?? null;
                
                // Log the user data we're getting from Telegram
                $this->line("📝 User data from Telegram API:");
                $this->line("   ID: {$userId}");
                $this->line("   First Name: " . ($firstName ?: 'NULL'));
                $this->line("   Last Name: " . ($lastName ?: 'NULL'));
                $this->line("   Username: " . ($username ?: 'NULL'));
                
                // Determine role based on admin status
                $adminStatus = $admin['status'];
                $role = in_array($adminStatus, ['creator', 'administrator']) ? 
                    UserManagement::ROLE_SUPERVISOR : 
                    UserManagement::ROLE_OPERATOR;
                
                // Create or update user
                $existingUser = UserManagement::where('telegram_user_id', $userId)->first();
                
                if ($existingUser) {
                    // Update user data along with role
                    $updateData = ['role' => $role];
                    
                    // Only update fields if they have values and current field is empty
                    if ($firstName && !$existingUser->first_name) {
                        $updateData['first_name'] = $firstName;
                    }
                    if ($lastName && !$existingUser->last_name) {
                        $updateData['last_name'] = $lastName;
                    }
                    if ($username && !$existingUser->username) {
                        $updateData['username'] = $username;
                    }
                    
                    $existingUser->update($updateData);
                    
                    if ($existingUser->role !== $role) {
                        $this->info("🔄 Updated {$firstName} {$lastName} role to {$role}");
                        $updated++;
                    } else {
                        $this->line("⏭️  {$firstName} {$lastName} already synced as {$role}");
                    }
                } else {
                    // Create new user - make sure we have at least first_name
                    if (!$firstName) {
                        $firstName = 'User'; // Fallback name
                    }
                    
                    UserManagement::create([
                        'telegram_chat_id' => $userId, // Use user ID for private chats
                        'telegram_user_id' => $userId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'username' => $username,
                        'role' => $role,
                        'status' => UserManagement::STATUS_ACTIVE,
                        'is_available_for_lunch' => $role === UserManagement::ROLE_OPERATOR,
                    ]);
                    
                    $this->info("✅ Synced {$firstName} {$lastName} as {$role}");
                    $synced++;
                }
            }
            
            // Try to get more group members (this might be limited by Telegram API)
            $this->info("\n🔍 Attempting to get more group members...");
            
            // Get group info to see member count
            $chatInfoUrl = "https://api.telegram.org/bot{$botToken}/getChat";
            $chatInfoData = ['chat_id' => $groupId];
            
            $chatResponse = $this->makeApiCall($chatInfoUrl, $chatInfoData);
            
            if ($chatResponse && $chatResponse['ok']) {
                $memberCount = $chatResponse['result']['member_count'] ?? 'Unknown';
                $this->info("📊 Total group members: {$memberCount}");
            }
            
            // Show summary
            $this->info("\n📊 Sync completed:");
            $this->info("✅ New users synced: {$synced}");
            $this->info("🔄 Users updated: {$updated}");
            
            // Show final counts
            $totalUsers = UserManagement::count();
            $operators = UserManagement::operators()->count();
            $supervisors = UserManagement::supervisors()->count();
            
            $this->info("\n📈 Current users_management stats:");
            $this->info("Total: {$totalUsers}");
            $this->info("Operators: {$operators}");
            $this->info("Supervisors: {$supervisors}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Group sync failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
    
    private function makeApiCall(string $url, array $data): ?array
    {
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
            return json_decode($response, true);
        }
        
        return null;
    }
}

