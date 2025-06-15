<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class LunchSchedule extends Model
{
    protected $fillable = [
        'date',
        'work_shift_id',
        'operator_queue',
        'current_position',
        'operators_per_group',
        'is_active',
    ];
    
    protected $casts = [
        'date' => 'date',
        'operator_queue' => 'array',
        'current_position' => 'integer',
        'operators_per_group' => 'integer',
        'is_active' => 'boolean',
    ];
    
    /**
     * Get the work shift
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }
    
    /**
     * Get lunch breaks for this schedule
     */
    public function lunchBreaks(): HasMany
    {
        return $this->hasMany(LunchBreak::class);
    }
    
    /**
     * Get current group of operators
     */
    public function getCurrentGroup(): array
    {
        $queue = $this->operator_queue ?? [];
        $start = $this->current_position;
        $end = min($start + $this->operators_per_group, count($queue));
        
        return array_slice($queue, $start, $end - $start);
    }
    
    /**
     * Get next group of operators
     */
    public function getNextGroup(): array
    {
        $queue = $this->operator_queue ?? [];
        $start = $this->current_position + $this->operators_per_group;
        $end = min($start + $this->operators_per_group, count($queue));
        
        if ($start >= count($queue)) {
            return [];
        }
        
        return array_slice($queue, $start, $end - $start);
    }
    
    /**
     * Move to next group
     */
    public function moveToNextGroup(): bool
    {
        $queue = $this->operator_queue ?? [];
        $nextPosition = $this->current_position + $this->operators_per_group;
        
        if ($nextPosition >= count($queue)) {
            return false; // No more groups
        }
        
        $this->current_position = $nextPosition;
        $this->save();
        
        return true;
    }
    
    /**
     * Reset queue to beginning
     */
    public function resetQueue(): void
    {
        $this->current_position = 0;
        $this->save();
    }
    
    /**
     * Add operator to queue
     */
    public function addOperatorToQueue(int $userId): void
    {
        $queue = $this->operator_queue ?? [];
        
        if (!in_array($userId, $queue)) {
            $queue[] = $userId;
            $this->operator_queue = $queue;
            $this->save();
        }
    }
    
    /**
     * Remove operator from queue
     */
    public function removeOperatorFromQueue(int $userId): void
    {
        $queue = $this->operator_queue ?? [];
        $queue = array_values(array_filter($queue, fn($id) => $id !== $userId));
        
        $this->operator_queue = $queue;
        $this->save();
    }
    
    /**
     * Reorder queue
     */
    public function reorderQueue(array $newQueue): void
    {
        $this->operator_queue = $newQueue;
        $this->current_position = 0;
        $this->save();
    }
    
    /**
     * Get total groups count
     */
    public function getTotalGroups(): int
    {
        $queue = $this->operator_queue ?? [];
        return (int) ceil(count($queue) / $this->operators_per_group);
    }
    
    /**
     * Get current group number
     */
    public function getCurrentGroupNumber(): int
    {
        return (int) floor($this->current_position / $this->operators_per_group) + 1;
    }
    
    /**
     * Check if schedule is for today
     */
    public function isToday(): bool
    {
        return $this->date->isToday();
    }
    
    /**
     * Scope: Active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope: Today's schedules
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', Carbon::today());
    }
}
