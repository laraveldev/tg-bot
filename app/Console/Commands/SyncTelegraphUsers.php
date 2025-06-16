<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserManagement;
use App\Services\Telegram\AdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncTelegraphUsers extends Command
{
    protected $signature = 'users:sync-telegraph';
    protected $description = 'Sync all telegraph_chats users to users_management table';

    public function handle()
    {
        $this->info('🔄 Syncing telegraph users to users_management...');
        
        // Get all telegraph chats that are private (positive chat_id means private)
        $telegraphUsers = DB::table('telegraph_chats')
            ->where('chat_id', '>', 0) // Only private chats
            ->get();
            
        $this->info("Found {$telegraphUsers->count()} telegraph users");
        
        $adminService = new AdminService();
        $synced = 0;
        $skipped = 0;
        
        foreach ($telegraphUsers as $telegraphUser) {
            try {
                // Check if user already exists in users_management
                $existingUser = UserManagement::where('telegram_chat_id', $telegraphUser->chat_id)
                    ->orWhere('telegram_user_id', $telegraphUser->chat_id)
                    ->first();
                    
                if ($existingUser) {
                    $this->line("⏭️  User {$telegraphUser->first_name} already exists, skipping...");
                    $skipped++;
                    continue;
                }
                
                // Try to determine if user is admin (this might not work for all cases)
                // For now, default to operator unless we know they're admin
                $role = UserManagement::ROLE_OPERATOR;
                
                // Create new user in users_management
                $newUser = UserManagement::create([
                    'telegram_chat_id' => $telegraphUser->chat_id,
                    'telegram_user_id' => $telegraphUser->chat_id,
                    'first_name' => $telegraphUser->first_name,
                    'last_name' => $telegraphUser->last_name,
                    'username' => $telegraphUser->username,
                    'phone_number' => $telegraphUser->phone_number ?? null,
                    'role' => $role,
                    'status' => UserManagement::STATUS_ACTIVE,
                    'is_available_for_lunch' => true,
                ]);
                
                $this->info("✅ Synced user: {$telegraphUser->first_name} {$telegraphUser->last_name} as {$role}");
                $synced++;
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to sync user {$telegraphUser->first_name}: {$e->getMessage()}");
                Log::error('User sync failed', [
                    'chat_id' => $telegraphUser->chat_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("\n📊 Sync completed:");
        $this->info("✅ Synced: {$synced}");
        $this->info("⏭️  Skipped: {$skipped}");
        
        // Show final counts
        $totalUsers = UserManagement::count();
        $operators = UserManagement::operators()->count();
        $supervisors = UserManagement::supervisors()->count();
        
        $this->info("\n📈 Current users_management stats:");
        $this->info("Total: {$totalUsers}");
        $this->info("Operators: {$operators}");
        $this->info("Supervisors: {$supervisors}");
        
        return 0;
    }
}

