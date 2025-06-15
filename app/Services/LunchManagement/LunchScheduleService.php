<?php

namespace App\Services\LunchManagement;

use App\Models\UserManagement;
use App\Models\WorkShift;
use App\Models\LunchSchedule;
use App\Models\LunchBreak;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Tushlik jadvali boshqaruv service
 * 
 * Bu service quyidagi vazifalarni bajaradi:
 * - Kunlik tushlik jadvali yaratish
 * - Operatorlar navbatini tuzish
 * - Navbatni boshqarish
 */
class LunchScheduleService
{
    /**
     * Bugungi kun uchun tushlik jadvali yaratish
     */
    public function createDailySchedule(WorkShift $workShift, ?array $operatorIds = null): LunchSchedule
    {
        $today = Carbon::today();
        
        // Agar jadval allaqachon mavjud bo'lsa, uni qaytarish
        $existingSchedule = LunchSchedule::where('date', $today)
            ->where('work_shift_id', $workShift->id)
            ->first();
            
        if ($existingSchedule) {
            return $existingSchedule;
        }
        
        // Operatorlar ro'yxatini olish
        if ($operatorIds === null) {
            $operators = $workShift->operators()
                ->where('status', UserManagement::STATUS_ACTIVE)
                ->where('is_available_for_lunch', true)
                ->orderBy('lunch_order')
                ->get();
            $operatorIds = $operators->pluck('id')->toArray();
        }
        
        // Jadval yaratish
        $schedule = LunchSchedule::create([
            'date' => $today,
            'work_shift_id' => $workShift->id,
            'operator_queue' => $operatorIds,
            'current_position' => 0,
            'operators_per_group' => $workShift->max_lunch_operators,
            'is_active' => true,
        ]);
        
        Log::info('Tushlik jadvali yaratildi', [
            'schedule_id' => $schedule->id,
            'work_shift' => $workShift->name,
            'operators_count' => count($operatorIds),
        ]);
        
        return $schedule;
    }
    
    /**
     * Operatorlar navbatini qayta tuzish
     */
    public function reorderQueue(LunchSchedule $schedule, array $newOperatorIds): bool
    {
        try {
            $schedule->reorderQueue($newOperatorIds);
            
            Log::info('Tushlik navbati qayta tuzildi', [
                'schedule_id' => $schedule->id,
                'new_queue' => $newOperatorIds,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Navbatni qayta tuzishda xatolik', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Keyingi guruhga o'tish
     */
    public function moveToNextGroup(LunchSchedule $schedule): bool
    {
        if (!$schedule->moveToNextGroup()) {
            Log::warning('Keyingi guruh mavjud emas', [
                'schedule_id' => $schedule->id,
                'current_position' => $schedule->current_position,
            ]);
            return false;
        }
        
        Log::info('Keyingi guruhga o\'tildi', [
            'schedule_id' => $schedule->id,
            'new_position' => $schedule->current_position,
            'group_number' => $schedule->getCurrentGroupNumber(),
        ]);
        
        return true;
    }
    
    /**
     * Hozirgi guruh operatorlarini olish
     */
    public function getCurrentGroupOperators(LunchSchedule $schedule): Collection
    {
        $currentGroupIds = $schedule->getCurrentGroup();
        
        return UserManagement::whereIn('id', $currentGroupIds)
            ->with('workShift')
            ->get();
    }
    
    /**
     * Keyingi guruh operatorlarini olish
     */
    public function getNextGroupOperators(LunchSchedule $schedule): Collection
    {
        $nextGroupIds = $schedule->getNextGroup();
        
        if (empty($nextGroupIds)) {
            return collect();
        }
        
        return UserManagement::whereIn('id', $nextGroupIds)
            ->with('workShift')
            ->get();
    }
    
    /**
     * Operator navbatdan chiqarish
     */
    public function removeOperatorFromQueue(LunchSchedule $schedule, int $operatorId): bool
    {
        try {
            $schedule->removeOperatorFromQueue($operatorId);
            
            Log::info('Operator navbatdan chiqarildi', [
                'schedule_id' => $schedule->id,
                'operator_id' => $operatorId,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Operatorni navbatdan chiqarishda xatolik', [
                'schedule_id' => $schedule->id,
                'operator_id' => $operatorId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Operator navbatga qo'shish
     */
    public function addOperatorToQueue(LunchSchedule $schedule, int $operatorId): bool
    {
        try {
            $schedule->addOperatorToQueue($operatorId);
            
            Log::info('Operator navbatga qo\'shildi', [
                'schedule_id' => $schedule->id,
                'operator_id' => $operatorId,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Operatorni navbatga qo\'shishda xatolik', [
                'schedule_id' => $schedule->id,
                'operator_id' => $operatorId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Jadval statistikasi
     */
    public function getScheduleStats(LunchSchedule $schedule): array
    {
        return [
            'date' => $schedule->date->format('Y-m-d'),
            'work_shift' => $schedule->workShift->name,
            'total_operators' => count($schedule->operator_queue ?? []),
            'current_group_number' => $schedule->getCurrentGroupNumber(),
            'total_groups' => $schedule->getTotalGroups(),
            'operators_per_group' => $schedule->operators_per_group,
            'current_position' => $schedule->current_position,
            'is_active' => $schedule->is_active,
        ];
    }
    
    /**
     * Bugungi barcha jadvallar
     */
    public function getTodaySchedules(): Collection
    {
        return LunchSchedule::today()
            ->with(['workShift', 'lunchBreaks.user'])
            ->get();
    }
    
    /**
     * Jadval yaratish yoki yangilash
     */
    public function createOrUpdateSchedule(
        WorkShift $workShift, 
        ?Carbon $date = null, 
        ?array $operatorIds = null,
        ?int $operatorsPerGroup = null
    ): LunchSchedule {
        $date = $date ?? Carbon::today();
        $operatorsPerGroup = $operatorsPerGroup ?? $workShift->max_lunch_operators;
        
        $schedule = LunchSchedule::updateOrCreate(
            [
                'date' => $date,
                'work_shift_id' => $workShift->id,
            ],
            [
                'operator_queue' => $operatorIds ?? [],
                'current_position' => 0,
                'operators_per_group' => $operatorsPerGroup,
                'is_active' => true,
            ]
        );
        
        return $schedule;
    }
}

