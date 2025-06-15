<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WorkShift;
use App\Models\UserManagement;
use Carbon\Carbon;

class LunchManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Work Shifts yaratish
        $morningShift = WorkShift::create([
            'name' => 'Erta smena',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'lunch_duration' => 30,
            'max_lunch_operators' => 2,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '15:00',
            'is_active' => true,
        ]);
        
        $eveningShift = WorkShift::create([
            'name' => 'Kech smena',
            'start_time' => '18:00',
            'end_time' => '03:00',
            'lunch_duration' => 30,
            'max_lunch_operators' => 1,
            'lunch_start_time' => '21:00',
            'lunch_end_time' => '24:00',
            'is_active' => true,
        ]);
        
        // Test foydalanuvchilar yaratish
        $users = [
            [
                'telegram_chat_id' => '5203861117', // Sizning ID
                'first_name' => 'Elnurbek',
                'last_name' => '',
                'username' => 'elnurbek',
                'role' => 'supervisor',
                'status' => 'active',
                'is_available_for_lunch' => false,
                'work_shift_id' => $morningShift->id,
            ],
            [
                'telegram_chat_id' => '123456789',
                'first_name' => 'Operator',
                'last_name' => '1',
                'username' => 'operator1',
                'role' => 'operator',
                'status' => 'active',
                'is_available_for_lunch' => true,
                'lunch_order' => 1,
                'work_shift_id' => $morningShift->id,
            ],
            [
                'telegram_chat_id' => '987654321',
                'first_name' => 'Operator',
                'last_name' => '2',
                'username' => 'operator2',
                'role' => 'operator',
                'status' => 'active',
                'is_available_for_lunch' => true,
                'lunch_order' => 2,
                'work_shift_id' => $morningShift->id,
            ],
            [
                'telegram_chat_id' => '111111111',
                'first_name' => 'Operator',
                'last_name' => '3',
                'username' => 'operator3',
                'role' => 'operator',
                'status' => 'active',
                'is_available_for_lunch' => true,
                'lunch_order' => 3,
                'work_shift_id' => $morningShift->id,
            ],
            [
                'telegram_chat_id' => '222222222',
                'first_name' => 'Operator',
                'last_name' => '4',
                'username' => 'operator4',
                'role' => 'operator',
                'status' => 'active',
                'is_available_for_lunch' => true,
                'lunch_order' => 4,
                'work_shift_id' => $morningShift->id,
            ],
        ];
        
        foreach ($users as $userData) {
            UserManagement::create($userData);
        }
        
        $this->command->info('Lunch management test data created successfully!');
        $this->command->info('Work Shifts: ' . WorkShift::count());
        $this->command->info('Users: ' . UserManagement::count());
    }
}
