<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserManagement;
use App\Models\WorkShift;

class AddTestOperators extends Command
{
    protected $signature = 'users:add-test-operators {--count=5}';
    protected $description = 'Add test operators to users_management table';

    public function handle()
    {
        $count = $this->option('count');
        $this->info("🔄 Adding {$count} test operators...");
        
        // Get first work shift
        $workShift = WorkShift::first();
        if (!$workShift) {
            $this->error('❌ No work shift found! Run seeder first.');
            return 1;
        }
        
        $operators = [
            ['first_name' => 'Operator', 'last_name' => 'Test1', 'username' => 'operator_test1'],
            ['first_name' => 'Operator', 'last_name' => 'Test2', 'username' => 'operator_test2'],
            ['first_name' => 'Operator', 'last_name' => 'Test3', 'username' => 'operator_test3'],
            ['first_name' => 'Operator', 'last_name' => 'Test4', 'username' => 'operator_test4'],
            ['first_name' => 'Operator', 'last_name' => 'Test5', 'username' => 'operator_test5'],
            ['first_name' => 'John', 'last_name' => 'Doe', 'username' => 'johndoe'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'username' => 'janesmith'],
            ['first_name' => 'Mike', 'last_name' => 'Johnson', 'username' => 'mikej'],
            ['first_name' => 'Sarah', 'last_name' => 'Wilson', 'username' => 'sarahw'],
            ['first_name' => 'Tom', 'last_name' => 'Brown', 'username' => 'tombrown'],
        ];
        
        $added = 0;
        
        for ($i = 0; $i < min($count, count($operators)); $i++) {
            $operatorData = $operators[$i];
            $chatId = 100000000 + $i + 1; // Fake chat IDs starting from 100000001
            
            // Check if operator already exists
            $existing = UserManagement::where('telegram_chat_id', $chatId)->first();
            if ($existing) {
                $this->line("⏭️  {$operatorData['first_name']} {$operatorData['last_name']} already exists");
                continue;
            }
            
            UserManagement::create([
                'telegram_chat_id' => $chatId,
                'telegram_user_id' => $chatId,
                'first_name' => $operatorData['first_name'],
                'last_name' => $operatorData['last_name'],
                'username' => $operatorData['username'],
                'role' => UserManagement::ROLE_OPERATOR,
                'status' => UserManagement::STATUS_ACTIVE,
                'is_available_for_lunch' => true,
                'lunch_order' => $i + 1,
                'work_shift_id' => $workShift->id,
            ]);
            
            $this->info("✅ Added: {$operatorData['first_name']} {$operatorData['last_name']} (@{$operatorData['username']})");
            $added++;
        }
        
        $this->info("\n📊 Summary:");
        $this->info("✅ Added {$added} test operators");
        
        // Show final stats
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

