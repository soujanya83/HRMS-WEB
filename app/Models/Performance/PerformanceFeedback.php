<?php

namespace App\Models\Performance;

use App\Models\Employee\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'giver_employee_id',
        'receiver_employee_id',
        'feedback_content',
        'type',
        'visibility',
        'performance_review_id',
        'performance_goal_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The employee who gave the feedback.
     */
    public function giver()
    {
        return $this->belongsTo(Employee::class, 'giver_employee_id');
    }

    /**
     * The employee who received the feedback.
     */
    public function receiver()
    {
        return $this->belongsTo(Employee::class, 'receiver_employee_id');
    }

    /**
     * The formal review this feedback is attached to (if any).
     */
    public function performanceReview()
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    /**
     * The goal this feedback is related to (if any).
     */
    public function performanceGoal()
    {
        return $this->belongsTo(PerformanceGoal::class);
    }
}