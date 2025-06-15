<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class WorkShift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'lunch_duration',
        'max_lunch_operators',
        'lunch_start_time',
        'lunch_end_time',
        'is_active',
    ];
    
    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'lunch_start_time' => 'datetime:H:i',
        'lunch_end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'lunch_duration' => 'integer',
        'max_lunch_operators' => 'integer',
    ];
    
    /**
     * Get users in this shift
     */
    public function users(): HasMany
    {
        return $this->hasMany(UserManagement::class);
    }
    
    /**
     * Get lunch schedules for this shift
     */
    public function lunchSchedules(): HasMany
    {
        return $this->hasMany(LunchSchedule::class);
    }
    
    /**
     * Get operators in this shift
     */
    public function operators(): HasMany
    {
        return $this->users()->where('role', UserManagement::ROLE_OPERATOR);
    }
    
    /**
     * Check if shift is currently active
     */
    public function isCurrentlyActive(): bool
    {
        $now = Carbon::now()->format('H:i');
        $start = Carbon::parse($this->start_time)->format('H:i');
        $end = Carbon::parse($this->end_time)->format('H:i');
        
        // Handle overnight shifts
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }
        
        return $now >= $start && $now <= $end;
    }
    
    /**
     * Check if it's lunch time
     */
    public function isLunchTime(): bool
    {
        if (!$this->lunch_start_time || !$this->lunch_end_time) {
            return false;
        }
        
        $now = Carbon::now()->format('H:i');
        $lunchStart = Carbon::parse($this->lunch_start_time)->format('H:i');
        $lunchEnd = Carbon::parse($this->lunch_end_time)->format('H:i');
        
        return $now >= $lunchStart && $now <= $lunchEnd;
    }
    
    /**
     * Get active operators count
     */
    public function getActiveOperatorsCount(): int
    {
        return $this->operators()
            ->where('status', UserManagement::STATUS_ACTIVE)
            ->count();
    }
    
    /**
     * Get operators on lunch break count
     */
    public function getOperatorsOnLunchCount(): int
    {
        return $this->operators()
            ->where('status', UserManagement::STATUS_LUNCH_BREAK)
            ->count();
    }
    
    /**
     * Can send more operators to lunch?
     */
    public function canSendMoreToLunch(): bool
    {
        return $this->getOperatorsOnLunchCount() < $this->max_lunch_operators;
    }
    
    /**
     * Scope: Active shifts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
