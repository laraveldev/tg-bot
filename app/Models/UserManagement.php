<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserManagement extends Model
{
    protected $table = 'users_management';
    
    protected $fillable = [
        'telegram_chat_id',
        'telegram_user_id',
        'first_name',
        'last_name',
        'username',
        'phone_number',
        'role',
        'status',
        'is_available_for_lunch',
        'lunch_order',
        'work_shift_id',
    ];
    
    protected $casts = [
        'is_available_for_lunch' => 'boolean',
        'lunch_order' => 'integer',
    ];
    
    // Role constants
    const ROLE_SUPERVISOR = 'supervisor';
    const ROLE_OPERATOR = 'operator';
    const ROLE_USER = 'user';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_LUNCH_BREAK = 'lunch_break';
    
    /**
     * Get the work shift for this user
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }
    
    /**
     * Get lunch breaks for this user
     */
    public function lunchBreaks(): HasMany
    {
        return $this->hasMany(LunchBreak::class, 'user_id');
    }
    
    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Check if user is supervisor
     */
    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }
    
    /**
     * Check if user is operator
     */
    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }
    
    /**
     * Check if user is on lunch break
     */
    public function isOnLunchBreak(): bool
    {
        return $this->status === self::STATUS_LUNCH_BREAK;
    }
    
    /**
     * Get current active lunch break
     */
    public function getCurrentLunchBreak()
    {
        return $this->lunchBreaks()
            ->where('status', 'started')
            ->whereNull('actual_end_time')
            ->first();
    }
    
    /**
     * Scope: Get supervisors
     */
    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }
    
    /**
     * Scope: Get operators
     */
    public function scopeOperators($query)
    {
        return $query->where('role', self::ROLE_OPERATOR);
    }
    
    /**
     * Scope: Get active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
    
    /**
     * Scope: Available for lunch
     */
    public function scopeAvailableForLunch($query)
    {
        return $query->where('is_available_for_lunch', true)
                    ->where('status', '!=', self::STATUS_LUNCH_BREAK);
    }
}
