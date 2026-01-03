<?php

namespace App\Models\Performance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalKeyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_goal_id',
        'description',
        'type',
        'start_value',
        'target_value',
        'current_value',
        'notes',
    ];

    protected $casts = [
        'start_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    /**
     * The parent goal (Objective) this key result belongs to.
     */
    public function performanceGoal()
    {
        return $this->belongsTo(PerformanceGoal::class);
    }
}