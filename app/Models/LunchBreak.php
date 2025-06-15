<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LunchBreak extends Model
{
    protected $fillable = [
        'user_id',
        'lunch_schedule_id',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'status',
        'notes',
        'reminder_sent',
        'supervisor_notified',
    ];
    
    protected $casts = [
        'scheduled_start_time' => 'datetime',
        'scheduled_end_time' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'reminder_sent' => 'boolean',
        'supervisor_notified' => 'boolean',
    ];
    
    // Status constants
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_REMINDED = 'reminded';
    const STATUS_STARTED = 'started';
    const STATUS_COMPLETED = 'completed';
    const STATUS_MISSED = 'missed';
    
    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserManagement::class);
    }
    
    /**
     * Get the lunch schedule
     */
    public function lunchSchedule(): BelongsTo
    {
        return $this->belongsTo(LunchSchedule::class);
    }
    
    /**
     * Start lunch break
     */
    public function startBreak(): void
    {
        $this->actual_start_time = Carbon::now();
        $this->status = self::STATUS_STARTED;
        $this->save();
        
        // Update user status
        $this->user->update(['status' => UserManagement::STATUS_LUNCH_BREAK]);
    }
    
    /**
     * End lunch break
     */
    public function endBreak(): void
    {
        $this->actual_end_time = Carbon::now();
        $this->status = self::STATUS_COMPLETED;
        $this->save();
        
        // Update user status
        $this->user->update(['status' => UserManagement::STATUS_ACTIVE]);
    }
    
    /**
     * Mark as missed
     */
    public function markAsMissed(): void
    {
        $this->status = self::STATUS_MISSED;
        $this->save();
    }
    
    /**
     * Send reminder
     */
    public function markReminderSent(): void
    {
        $this->reminder_sent = true;
        $this->status = self::STATUS_REMINDED;
        $this->save();
    }
    
    /**
     * Notify supervisor
     */
    public function markSupervisorNotified(): void
    {
        $this->supervisor_notified = true;
        $this->save();
    }
    
    /**
     * Get duration in minutes
     */
    public function getDurationInMinutes(): ?int
    {
        if (!$this->actual_start_time || !$this->actual_end_time) {
            return null;
        }
        
        return $this->actual_start_time->diffInMinutes($this->actual_end_time);
    }
    
    /**
     * Get scheduled duration in minutes
     */
    public function getScheduledDurationInMinutes(): int
    {
        return $this->scheduled_start_time->diffInMinutes($this->scheduled_end_time);
    }
    
    /**
     * Check if break is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_STARTED) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->scheduled_end_time);
    }
    
    /**
     * Check if reminder should be sent (5 minutes before)
     */
    public function shouldSendReminder(): bool
    {
        if ($this->reminder_sent || $this->status !== self::STATUS_SCHEDULED) {
            return false;
        }
        
        $reminderTime = $this->scheduled_start_time->subMinutes(5);
        return Carbon::now()->gte($reminderTime);
    }
    
    /**
     * Check if break is currently active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_STARTED;
    }
    
    /**
     * Scope: Active breaks
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_STARTED);
    }
    
    /**
     * Scope: Today's breaks
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_start_time', Carbon::today());
    }
    
    /**
     * Scope: Pending reminders
     */
    public function scopePendingReminders($query)
    {
        return $query->where('reminder_sent', false)
                    ->where('status', self::STATUS_SCHEDULED)
                    ->where('scheduled_start_time', '>', Carbon::now())
                    ->where('scheduled_start_time', '<=', Carbon::now()->addMinutes(5));
    }
    
    /**
     * Scope: Overdue breaks
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_STARTED)
                    ->where('scheduled_end_time', '<', Carbon::now());
    }
}
