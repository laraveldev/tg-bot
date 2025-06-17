<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserManagement;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Log;

class FixNullUserData extends Command
{
    protected $signature = 'users:fix-null-data';
    protected $description = 'Fix NULL user data by fetching from Telegram API';

    public function handle()
    {
        $this->info('🔧 Fixing NULL user data...');
        
        $bot = TelegraphBot::first();
        if (!$bot) {
            $this->error('❌ No bot found!');
            return 1;
        }
        
        $botToken = $bot->token;
        $fixedCount = 0;
        
        // Get users with NULL first_name
        $usersWithNullData = UserManagement::where(function($query) {
            $query->whereNull('first_name')
                  ->orWhere('first_name', '')
                  ->orWhere('first_name', 'NULL');
        })->get();
        
        $this->info("Found {$usersWithNullData->count()} users with NULL/empty first_name");
        
        foreach ($usersWithNullData as $user) {
            if (!$user->telegram_user_id) {
                $this->warn("Skipping user {$user->id} - no telegram_user_id");
                continue;
            }
            
            try {
                // Try to get user info from any group they're in
                $groupId = config('telegraph.chat_id', '-1002768510963');
                $url = "https://api.telegram.org/bot{$botToken}/getChatMember";
                $data = [
                    'chat_id' => $groupId,
                    'user_id' => $user->telegram_user_id
                ];
                
                $response = $this->makeApiCall($url, $data);
                
                if ($response && $response['ok'] && isset($response['result']['user'])) {
                    $telegramUser = $response['result']['user'];
                    
                    $firstName = $telegramUser['first_name'] ?? null;
                    $lastName = $telegramUser['last_name'] ?? null;
                    $username = $telegramUser['username'] ?? null;
                    
                    $this->line("📝 Telegram data for user {$user->telegram_user_id}:");
                    $this->line("   First Name: " . ($firstName ?: 'NULL'));
                    $this->line("   Last Name: " . ($lastName ?: 'NULL'));
                    $this->line("   Username: " . ($username ?: 'NULL'));
                    
                    $updateData = [];
                    
                    // Update fields if we have data
                    if ($firstName && (!$user->first_name || $user->first_name === 'NULL')) {
                        $updateData['first_name'] = $firstName;
                    }
                    if ($lastName && (!$user->last_name || $user->last_name === 'NULL')) {
                        $updateData['last_name'] = $lastName;
                    }
                    if ($username && (!$user->username || $user->username === 'NULL')) {
                        $updateData['username'] = $username;
                    }
                    
                    // If still no first_name, create a fallback
                    if (!isset($updateData['first_name']) && (!$user->first_name || $user->first_name === 'NULL')) {
                        if ($lastName) {
                            $updateData['first_name'] = $lastName;
                        } elseif ($username) {
                            $updateData['first_name'] = $username;
                        } else {
                            $updateData['first_name'] = 'User ' . $user->telegram_user_id;
                        }
                    }
                    
                    if (!empty($updateData)) {
                        $user->update($updateData);
                        $this->info("✅ Updated user {$user->telegram_user_id}: " . json_encode($updateData));
                        $fixedCount++;
                    } else {
                        $this->warn("⚠️  No data to update for user {$user->telegram_user_id}");
                    }
                } else {
                    // User might not be in the group anymore, create fallback name
                    if (!$user->first_name || $user->first_name === 'NULL') {
                        $fallbackName = 'User ' . $user->telegram_user_id;
                        $user->update(['first_name' => $fallbackName]);
                        $this->info("🔄 Created fallback name for user {$user->telegram_user_id}: {$fallbackName}");
                        $fixedCount++;
                    }
                }
                
                // Small delay to avoid hitting rate limits
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $this->error("❌ Error processing user {$user->telegram_user_id}: {$e->getMessage()}");
            }
        }
        
        $this->info("\n🎉 Fixed {$fixedCount} users");
        
        // Show updated stats
        $this->info("\n📊 Updated user list:");
        UserManagement::all()->each(function($user) {
            $name = trim(($user->first_name ?: 'NULL') . ' ' . ($user->last_name ?: ''));
            $this->line("ID: {$user->telegram_user_id} | Name: {$name} | Username: " . ($user->username ?: 'NULL') . " | Role: {$user->role}");
        });
        
        return 0;
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

