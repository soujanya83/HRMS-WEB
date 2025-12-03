<?php

namespace App\Models\Performance;

use App\Models\Employee\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceGoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'review_cycle_id',
        'title',
        'description',
        'start_date',
        'due_date',
        'status',
        'manager_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The employee who owns this goal.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * The manager who set/approved this goal.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * The review cycle this goal is part of (if any).
     */
    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }

    /**
     * The key results (KPIs) that measure this goal.
     */
    public function keyResults()
    {
        return $this->hasMany(GoalKeyResult::class);
    }

    /**
     * Any continuous feedback linked to this goal.
     */
    public function feedback()
    {
        return $this->hasMany(PerformanceFeedback::class);
    }
}